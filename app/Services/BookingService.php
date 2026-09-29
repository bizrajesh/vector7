<?php

namespace App\Services;

use App\Enums\PlotStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Plot;
use Illuminate\Support\Facades\DB;

/**
 * Booking (section 6.3): holds an Available plot for N days (default 15).
 */
class BookingService
{
    public function __construct(
        private readonly PlotStatusMachine $machine,
        private readonly PaymentRecorder $payments,
        private readonly Settings $settings,
        private readonly Notifier $notifier,
    ) {}

    public function book(Plot $plot, Customer $customer, array $payment): Booking
    {
        return DB::transaction(function () use ($plot, $customer, $payment) {
            // Row lock prevents two users booking the same plot at once (race condition).
            $plot = Plot::query()->whereKey($plot->id)->lockForUpdate()->firstOrFail();
            abort_unless($plot->status === PlotStatus::Available, 422, 'This plot is no longer available.');
            abort_unless($plot->layout->status->value === 'launched', 422, 'This project is not launched yet.');

            $booking = new Booking(['amount' => $payment['amount']]);
            $booking->plot_id = $plot->id;
            $booking->customer_id = $customer->id;
            $booking->booked_at = now();
            $booking->expires_at = now()->addDays((int) $this->settings->get('booking_validity_days', 15))->endOfDay();
            $booking->status = 'active';
            $booking->created_by = auth()->id();
            $booking->save();

            $this->machine->transition($plot, PlotStatus::Booked, "Booked by {$customer->name}");
            $this->payments->record($customer, $payment, $plot, booking: $booking);

            $this->notifier->event('booking.created', "Plot {$plot->plot_no} ({$plot->layout->name}) booked by {$customer->name} until {$booking->expires_at->format('d M Y')}.", $plot->layout);
            $this->notifier->customer($customer, 'booking.created', "Your booking for plot {$plot->plot_no} is confirmed. Valid till {$booking->expires_at->format('d M Y')}.");

            return $booking;
        });
    }

    /** Release bookings past expiry that were not converted to a sale (daily job). */
    public function expireDue(): int
    {
        $count = 0;
        Booking::query()->whereIn('status', ['pending', 'active'])->where('expires_at', '<', now())->lazyById()->each(function (Booking $booking) use (&$count) {
            DB::transaction(function () use ($booking, &$count) {
                $plot = Plot::query()->whereKey($booking->plot_id)->lockForUpdate()->first();
                $booking->status = 'expired';
                $booking->save();

                if ($plot && $plot->status === PlotStatus::Booked) {
                    $this->machine->transition($plot, PlotStatus::Available, 'Booking expired');
                }
                $this->notifier->event('booking.expired', "Booking for plot {$plot?->plot_no} expired and the plot is available again.", $plot?->layout);
                $count++;
            });
        });

        return $count;
    }

    public function cancel(Booking $booking, string $reason): void
    {
        abort_unless(in_array($booking->status, ['pending', 'active'], true), 422, 'Only an open booking can be cancelled.');

        DB::transaction(function () use ($booking, $reason) {
            $plot = Plot::query()->whereKey($booking->plot_id)->lockForUpdate()->firstOrFail();
            $booking->status = 'cancelled';
            $booking->save();
            $this->machine->transition($plot, PlotStatus::Available, 'Booking cancelled: '.$reason);
        });
    }
}
