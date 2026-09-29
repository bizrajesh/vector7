<?php

namespace App\Services;

use App\Enums\PlotStatus;
use App\Models\Booking;
use App\Models\Broker;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plot;
use App\Models\Sale;
use App\Models\SaleInstalment;
use App\Support\SecurityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buy (section 6.4): 30/60/10 instalments within 15 working days,
 * Ongoing-Sale until fully paid, then ROR.
 */
class SaleService
{
    public function __construct(
        private readonly PlotStatusMachine $machine,
        private readonly PaymentRecorder $payments,
        private readonly LedgerService $ledger,
        private readonly WorkingDays $workingDays,
        private readonly Settings $settings,
        private readonly Notifier $notifier,
        private readonly ShareService $shares,
    ) {}

    public function create(Plot $plot, Customer $customer, array $data, ?array $firstPayment = null): Sale
    {
        return DB::transaction(function () use ($plot, $customer, $data, $firstPayment) {
            $plot = Plot::query()->whereKey($plot->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($plot->status, [PlotStatus::Available, PlotStatus::Booked], true), 422, 'Only Available or Booked plots can be sold.');
            abort_unless($plot->layout->status->value === 'launched', 422, 'This project is not launched yet.');

            $booking = null;
            if ($plot->status === PlotStatus::Booked) {
                $booking = Booking::query()->where('plot_id', $plot->id)->whereIn('status', ['pending', 'active'])->lockForUpdate()->first();
                if ($booking && $booking->customer_id !== $customer->id) {
                    $override = trim((string) ($data['override_reason'] ?? ''));
                    if (! auth()->user()->hasRole('admin') || $override === '') {
                        throw ValidationException::withMessages(['customer_id' => 'This plot is booked by another customer. Only an Admin can override, with a reason.']);
                    }
                    SecurityLog::info('booking_customer_override', ['plot_id' => $plot->id, 'reason' => $override]);
                }
            }

            $broker = ! empty($data['broker_id']) ? Broker::query()->findOrFail($data['broker_id']) : null;
            $commissionPct = $broker
                ? (float) ($broker->commission_pct ?? $this->settings->get('broker_commission_pct', 0.5))
                : 0.0;

            $sale = new Sale(['sale_value' => $plot->cost]);
            $sale->plot_id = $plot->id;
            $sale->customer_id = $customer->id;
            $sale->booking_id = $booking?->id;
            $sale->broker_id = $broker?->id;
            $sale->commission_pct = $commissionPct;
            $sale->commission_amount = round((float) $plot->cost * $commissionPct / 100, 2);
            $sale->sale_date = now()->toDateString();
            $sale->created_by = auth()->id();

            $schedule = $this->buildSchedule((float) $plot->cost);
            $sale->due_by = end($schedule)['due_date'];
            $sale->status = 'ongoing';
            $sale->save();

            foreach ($schedule as $row) {
                $instalment = new SaleInstalment($row);
                $instalment->sale_id = $sale->id;
                $instalment->save();
            }

            if ($booking) {
                // The booking advance counts towards the first instalment.
                Payment::query()->where('booking_id', $booking->id)->update(['sale_id' => $sale->id]);
                $booking->status = 'converted';
                $booking->save();
            }

            $this->machine->transition($plot, PlotStatus::OngoingSale, "Sale to {$customer->name}");

            if ($firstPayment && (float) $firstPayment['amount'] > 0) {
                $this->assertWithinBalance($sale, (float) $firstPayment['amount']);
                $this->payments->record($customer, $firstPayment, $plot, sale: $sale);
            }

            $this->recalculate($sale->fresh());

            return $sale->fresh();
        });
    }

    public function recordPayment(Sale $sale, array $data): Payment
    {
        return DB::transaction(function () use ($sale, $data) {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            abort_unless($sale->status === 'ongoing', 422, 'This sale is not accepting payments.');
            $this->assertWithinBalance($sale, (float) $data['amount']);

            $payment = $this->payments->record($sale->customer, $data, $sale->plot, sale: $sale);
            $this->recalculate($sale);

            return $payment;
        });
    }

    public function cancel(Sale $sale, string $reason): void
    {
        abort_unless($sale->status === 'ongoing', 422, 'Only an ongoing sale can be cancelled.');

        DB::transaction(function () use ($sale, $reason) {
            $plot = Plot::query()->whereKey($sale->plot_id)->lockForUpdate()->firstOrFail();
            $sale->status = 'cancelled';
            $sale->cancel_reason = $reason;
            $sale->save();
            $this->machine->transition($plot, PlotStatus::Available, 'Sale cancelled: '.$reason);
        });
    }

    /** Recompute paid amounts, instalment states and plot status from payments (single source of truth). */
    public function recalculate(Sale $sale): void
    {
        $paid = round((float) $sale->payments()->sum('amount'), 2);
        $sale->paid_amount = $paid;

        $remaining = $paid;
        foreach ($sale->instalments()->get() as $instalment) {
            $applied = min($remaining, (float) $instalment->amount);
            $remaining = round($remaining - $applied, 2);
            $instalment->paid_amount = $applied;
            $instalment->status = $applied >= (float) $instalment->amount ? 'paid' : ($applied > 0 ? 'partial' : 'due');
            $instalment->save();
        }

        $sale->save();

        if ($sale->balance() <= 0 && $sale->status === 'ongoing') {
            $sale->status = 'paid';
            $sale->save();

            $plot = Plot::query()->whereKey($sale->plot_id)->lockForUpdate()->firstOrFail();
            $this->machine->transition($plot, PlotStatus::Ror, 'Fully paid');

            if ((float) $sale->commission_amount > 0) {
                $this->ledger->post([
                    'layout_id' => $plot->layout_id, 'direction' => 'out', 'type' => 'commission',
                    'category' => 'Broker commission', 'amount' => $sale->commission_amount,
                    'party' => $sale->broker?->name, 'description' => 'Broker commission for plot '.$plot->plot_no,
                ], $sale);
            }

            $this->notifier->event('plot.ror', "Plot {$plot->plot_no} is fully paid and ready for registration.", $plot->layout);
            $this->notifier->customer($sale->customer, 'plot.ror', "Thank you. Plot {$plot->plot_no} is fully paid. We will contact you for registration.");

            if ($this->settings->get('share_recognition') === 'ror') {
                $this->shares->recogniseSale($sale);
            }
        }
    }

    /** @return array<int, array{seq:int,pct:float,amount:float,due_date:string}> */
    private function buildSchedule(float $value): array
    {
        $rows = [];
        $allocated = 0.0;
        $plan = $this->settings->get('instalments');
        $last = count($plan) - 1;

        foreach (array_values($plan) as $i => $step) {
            $amount = $i === $last ? round($value - $allocated, 2) : round($value * $step['pct'] / 100, 2);
            $allocated += $amount;
            $rows[] = [
                'seq' => $i + 1,
                'pct' => $step['pct'],
                'amount' => $amount,
                'due_date' => $this->workingDays->add(now(), (int) $step['due_working_day'])->toDateString(),
            ];
        }

        return $rows;
    }

    private function assertWithinBalance(Sale $sale, float $amount): void
    {
        $balance = round((float) $sale->sale_value - (float) $sale->payments()->sum('amount'), 2);
        if ($amount <= 0 || $amount > $balance) {
            throw ValidationException::withMessages(['amount' => 'Amount must be between ₹1 and the balance of '.number_format($balance, 2).'.']);
        }
    }
}
