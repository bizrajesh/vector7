<?php

namespace App\Services;

use App\Enums\PlotStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Plot;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Website bookings: the customer places a short HOLD (default 48 h). The sales team
 * confirms it by recording the advance, which turns it into a normal 15-day booking.
 * Purchases are never completed online — only by the sales team.
 */
class OnlineBookingService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PlotStatusMachine $machine,
        private readonly PaymentRecorder $payments,
        private readonly Settings $settings,
        private readonly Notifier $notifier,
    ) {}

    /** @return array{0: Booking, 1: User} */
    public function hold(Plot $plot, array $person): array
    {
        return DB::transaction(function () use ($plot, $person) {
            $plot = Plot::query()->whereKey($plot->id)->lockForUpdate()->firstOrFail();
            if ($plot->status !== PlotStatus::Available) {
                throw ValidationException::withMessages(['plot' => 'Sorry, this plot was just taken. Please choose another plot.']);
            }

            $email = strtolower($person['email']);
            $user = User::query()->where('email', $email)->first();
            if ($user && ! ($user->role === Role::Customer && $user->tenant_id === $this->context->id())) {
                throw ValidationException::withMessages(['email' => 'This email is already used for another account. Please use a different email.']);
            }

            // One open online hold per customer per project keeps the catalogue fair.
            $customer = $user?->customer ?? Customer::query()->where('email', $email)->first();
            if ($customer && Booking::query()->where('customer_id', $customer->id)->where('status', 'pending')
                ->whereHas('plot', fn ($q) => $q->where('layout_id', $plot->layout_id))->exists()) {
                throw ValidationException::withMessages(['plot' => 'You already hold a plot in this project. Our sales team will contact you.']);
            }

            if (! $customer) {
                $customer = Customer::create(['name' => $person['name'], 'phone' => $person['phone'], 'email' => $email]);
            }

            if (! $user) {
                $user = new User(['name' => $person['name'], 'email' => $email, 'phone' => $person['phone'], 'password' => Str::password(32)]);
                $user->tenant_id = $this->context->id();
                $user->role = Role::Customer;
                $user->status = 'active';
                $user->customer_id = $customer->id;
                $user->email_verified_at = now(); // proven by the one-time code
                $user->save();
            }

            $booking = new Booking(['amount' => 0]);
            $booking->plot_id = $plot->id;
            $booking->customer_id = $customer->id;
            $booking->booked_at = now();
            $booking->expires_at = now()->addHours((int) $this->settings->get('online_hold_hours', 48));
            $booking->status = 'pending';
            $booking->source = 'online';
            $booking->save();

            $this->machine->transition($plot, PlotStatus::Booked, 'Online hold by '.$customer->name);

            $this->notifier->event('booking.online', "Website hold: plot {$plot->plot_no} ({$plot->layout->name}) by {$customer->name}, {$customer->phone}. Confirm the advance before {$booking->expires_at->format('d M H:i')}.", $plot->layout);
            $this->notifier->customer($customer, 'booking.online', "Plot {$plot->plot_no} is held for you until {$booking->expires_at->format('d M Y, h:i A')}. Our sales team will call you to collect the booking advance.");

            return [$booking, $user];
        });
    }

    /** Sales confirms a website hold by recording the booking advance. */
    public function confirm(Booking $booking, array $payment): void
    {
        DB::transaction(function () use ($booking, $payment) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($booking->status === 'pending', 422, 'This booking is already confirmed or closed.');

            $booking->amount = $payment['amount'];
            $booking->status = 'active';
            $booking->expires_at = now()->addDays((int) $this->settings->get('booking_validity_days', 15))->endOfDay();
            $booking->created_by = auth()->id();
            $booking->save();

            $this->payments->record($booking->customer, $payment, $booking->plot, booking: $booking);
            $this->notifier->customer($booking->customer, 'booking.created', "Your booking for plot {$booking->plot->plot_no} is confirmed. Valid till {$booking->expires_at->format('d M Y')}.");
        });
    }

    public function requestPurchase(array $data): PurchaseRequest
    {
        $request = PurchaseRequest::create($data);
        $plotText = $request->plot ? "plot {$request->plot->plot_no}" : 'a plot';
        $what = $request->type === 'callback' ? 'Call-back request' : 'Purchase request';

        $this->notifier->event('purchase.requested', "{$what} for {$plotText} from {$request->name}, {$request->phone}.", $request->layout);

        return $request;
    }
}
