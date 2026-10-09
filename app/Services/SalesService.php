<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Instalment;
use App\Models\InstalmentPlan;
use App\Models\Payment;
use App\Models\Plot;
use App\Models\PromoCode;
use App\Models\PromoCodeUse;
use App\Models\Receipt;
use App\Models\Refund;
use App\Models\RefundPenaltyRule;
use App\Models\Sale;
use App\Models\Tenant;
use App\Support\Format;
use App\Support\Pdf;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Booking & sales rules (spec 7.5):
 *  - one customer per plot at a time: SELECT … FOR UPDATE on the plot + a unique active-lock column;
 *  - booking valid for N working days; 1st instalment (30%) not paid → released by the scheduler;
 *  - sale on a booked plot only for the same customer; initial payment ≥ 1st instalment → Sale Init;
 *  - partial payments within the sale completion window; due = 0 → ROR (never before);
 *  - missed window → refund = paid − penalty (table), Admin approval, expense entry, plot released.
 */
class SalesService
{
    private static function fail(string $field, string $msg): never
    {
        throw ValidationException::withMessages([$field => $msg]);
    }

    /** Price on a date: offer price if active that day, else actual; promo discount on top. */
    public static function pricing(Plot $plot, Carbon $on, ?PromoCode $promo): array
    {
        $actual = $plot->actualPrice();
        $offer = $plot->offerIsActive($on) ? $plot->offerPrice() : null;
        $used = $offer ?? $actual;
        $discount = $promo ? $promo->discountOn($used) : 0.0;

        return ['actual_price' => $actual, 'offer_price' => $offer, 'offer_text' => $offer ? $plot->offer_text : null, 'price_used' => $used, 'discount_amount' => $discount, 'net_price' => round($used - $discount, 2)];
    }

    public static function findPromo(int $tenantId, ?string $code, int $projectId): ?PromoCode
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $promo = PromoCode::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $code)->first();
        if (! $promo || ! $promo->isUsableFor($projectId)) {
            self::fail('promo_code', 'This promo code is not valid for this plot.');
        }

        return $promo;
    }

    public static function book(Plot $plot, Customer $customer, string $source, ?string $promoCode, ?string $ip, ?int $userId = null): Booking
    {
        $tenant = Tenant::findOrFail($plot->tenant_id);

        return app(Tenancy::class)->run($tenant, function () use ($plot, $customer, $source, $promoCode, $ip, $userId, $tenant) {
            $booking = DB::transaction(function () use ($plot, $customer, $source, $promoCode, $ip, $userId, $tenant) {
                /** @var Plot $locked */
                $locked = Plot::whereKey($plot->id)->lockForUpdate()->firstOrFail();
                if ($locked->project->status !== 'launched') {
                    self::fail('plot', 'This project is not open for booking.');
                }
                if ($locked->status !== 'available') {
                    self::fail('plot', "Plot {$locked->plot_no} is no longer available.");
                }
                if (Booking::where('active_plot_lock', $locked->id)->exists() || Sale::where('active_plot_lock', $locked->id)->exists()) {
                    self::fail('plot', "Plot {$locked->plot_no} is already booked.");
                }
                $promo = self::findPromo($tenant->id, $promoCode, $locked->project_id);
                $today = today();
                $settings = $tenant->setting();
                $b = Booking::create(self::pricing($locked, $today, $promo) + [
                    'tenant_id' => $tenant->id,
                    'booking_no' => IdGenerator::next($tenant->id, 'booking'),
                    'project_id' => $locked->project_id,
                    'plot_id' => $locked->id,
                    'customer_id' => $customer->id,
                    'status' => 'active',
                    'source' => $source,
                    'booked_on' => $today,
                    'valid_till' => WorkingDays::for($tenant->id)->add($today, (int) $settings->booking_validity_days),
                    'promo_code_id' => $promo?->id,
                    'disclaimer_accepted_at' => now(),
                    'disclaimer_ip' => $ip,
                    'active_plot_lock' => $locked->id,
                    'created_by' => $userId,
                ]);
                if ($promo) {
                    PromoCodeUse::create(['tenant_id' => $tenant->id, 'promo_code_id' => $promo->id, 'customer_id' => $customer->id, 'booking_id' => $b->id, 'discount_amount' => $b->discount_amount]);
                    $promo->increment('used_count');
                }
                $locked->moveTo('booked', 'Booking '.$b->booking_no);
                $customer->associateWith($tenant->id, 'booking');

                return $b;
            });
            $booking->load('plot', 'project');
            $data = [
                'name' => $customer->name, 'booking_no' => $booking->booking_no, 'plot_no' => $booking->plot->plot_no, 'project' => $booking->project->name,
                'price' => Format::inr($booking->net_price), 'first_amount' => Format::inr($booking->firstInstalmentAmount()), 'valid_till' => Format::date($booking->valid_till),
            ];
            Notify::send('booking_created', [$customer->email], $data, $tenant->id);
            Notify::groups($tenant->id, ['Sales Team'], 'booking_created', $data);

            return $booking;
        });
    }

    /** Release a booking (expiry or cancellation): plot back to Available, promo use returned. */
    public static function releaseBooking(Booking $booking, string $status, string $reason): void
    {
        DB::transaction(function () use ($booking, $status, $reason) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->first();
            if ($b->status !== 'active') {
                return;
            }
            $b->update(['status' => $status, 'active_plot_lock' => null, 'released_at' => now()]);
            $plot = Plot::whereKey($b->plot_id)->lockForUpdate()->first();
            if ($plot->status === 'booked') {
                $plot->moveTo('available', $reason);
            }
            if ($b->promo_code_id) {
                PromoCodeUse::where('booking_id', $b->id)->delete();
                PromoCode::whereKey($b->promo_code_id)->where('used_count', '>', 0)->decrement('used_count');
            }
        });
    }

    /**
     * Initiate a sale (Sales only). Direct on an Available plot, or on a Booked plot for the same customer.
     * The initial payment must be at least the 1st instalment (30%).
     *
     * @param  array{amount:float, mode:string, reference_no:?string, paid_on:string, notes:?string}  $payment
     */
    public static function initiateSale(Plot $plot, Customer $customer, array $payment, ?int $brokerId, ?string $promoCode, ?string $ip, int $userId, ?UploadedFile $proof = null): Sale
    {
        $tenant = Tenant::findOrFail($plot->tenant_id);
        $settings = $tenant->setting();
        $plan = InstalmentPlan::where('tenant_id', $tenant->id)->orderBy('seq')->get();
        if ($plan->isEmpty() || abs($plan->sum('percent') - 100) > 0.01) {
            self::fail('amount', 'Set up an instalment plan totalling 100% in Settings first.');
        }

        $sale = DB::transaction(function () use ($plot, $customer, $payment, $brokerId, $promoCode, $ip, $userId, $tenant, $settings, $plan, $proof) {
            $locked = Plot::whereKey($plot->id)->lockForUpdate()->firstOrFail();
            $booking = null;
            if ($locked->status === 'booked') {
                $booking = Booking::where('plot_id', $locked->id)->where('status', 'active')->lockForUpdate()->first();
                if (! $booking) {
                    self::fail('plot', 'The booking for this plot is no longer active.');
                }
                if ($booking->customer_id !== $customer->id) {
                    self::fail('customer_id', "Plot {$locked->plot_no} is booked by another customer. Only that customer can buy it.");
                }
                $prices = $booking->only(['actual_price', 'offer_price', 'price_used', 'discount_amount', 'net_price']);
                $promoId = $booking->promo_code_id;
            } elseif ($locked->status === 'available') {
                if (Sale::where('active_plot_lock', $locked->id)->exists() || Booking::where('active_plot_lock', $locked->id)->exists()) {
                    self::fail('plot', 'This plot is locked by another transaction.');
                }
                $promo = self::findPromo($tenant->id, $promoCode, $locked->project_id);
                $prices = self::pricing($locked, today(), $promo);
                unset($prices['offer_text']);
                $promoId = $promo?->id;
            } else {
                self::fail('plot', "Plot {$locked->plot_no} is {$locked->statusLabel()} and cannot be sold.");
            }

            $net = (float) $prices['net_price'];
            $first = round($net * (float) $plan->first()->percent / 100, 2);
            if ((float) $payment['amount'] + 0.001 < $first) {
                self::fail('amount', 'The initial payment must be at least the 1st instalment of '.Format::inr($first).' ('.(float) $plan->first()->percent.'% of '.Format::inr($net).').');
            }
            if ((float) $payment['amount'] > $net + 0.001) {
                self::fail('amount', 'The payment is more than the plot price.');
            }
            $wd = WorkingDays::for($tenant->id);
            $start = today();
            $broker = $brokerId ? \App\Models\Broker::where('id', $brokerId)->where('is_active', true)->first() : null;
            $commPct = $broker ? (float) ($broker->commission_pct ?? $settings->broker_commission_pct) : 0;

            $sale = Sale::create($prices + [
                'tenant_id' => $tenant->id,
                'sale_no' => IdGenerator::next($tenant->id, 'sale'),
                'booking_id' => $booking?->id,
                'project_id' => $locked->project_id,
                'plot_id' => $locked->id,
                'customer_id' => $customer->id,
                'status' => 'sale_init',
                'started_on' => $start,
                'window_end' => $wd->add($start, (int) $settings->sale_window_days),
                'promo_code_id' => $promoId,
                'paid_amount' => 0,
                'due_amount' => $net,
                'broker_id' => $broker?->id,
                'broker_commission_pct' => $commPct,
                'broker_commission_amount' => round($net * $commPct / 100, 2),
                'disclaimer_accepted_at' => now(),
                'disclaimer_ip' => $ip,
                'active_plot_lock' => $locked->id,
                'created_by' => $userId,
            ]);
            $allocated = 0.0;
            foreach ($plan as $i => $row) {
                $amount = $i === $plan->count() - 1 ? round($net - $allocated, 2) : round($net * (float) $row->percent / 100, 2);
                $allocated += $amount;
                Instalment::create([
                    'tenant_id' => $tenant->id, 'sale_id' => $sale->id, 'seq' => $row->seq, 'name' => $row->name, 'percent' => $row->percent,
                    'amount' => $amount, 'due_date' => $wd->add($start, (int) $row->due_working_days),
                ]);
            }
            if ($booking) {
                $booking->update(['status' => 'converted', 'active_plot_lock' => null]);
            } elseif ($promoId) {
                PromoCodeUse::create(['tenant_id' => $tenant->id, 'promo_code_id' => $promoId, 'customer_id' => $customer->id, 'sale_id' => $sale->id, 'discount_amount' => $sale->discount_amount]);
                PromoCode::whereKey($promoId)->increment('used_count');
            }
            if ($broker && $sale->broker_commission_amount > 0) {
                Expense::create([
                    'tenant_id' => $tenant->id, 'transaction_no' => IdGenerator::next($tenant->id, 'expense'), 'project_id' => $locked->project_id,
                    'expense_category_id' => ExpenseCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Broker commission'])->id,
                    'vendor' => $broker->name, 'description' => "Broker commission {$commPct}% on sale {$sale->sale_no} (plot {$locked->plot_no})",
                    'amount' => $sale->broker_commission_amount, 'spent_on' => $start, 'created_by' => $userId,
                ]);
            }
            $locked->moveTo('sale_init', 'Sale '.$sale->sale_no);
            $customer->associateWith($tenant->id, 'sale');
            $sale->setRelation('firstPayment', self::storePayment($sale, $payment, $userId, $proof, $booking ? 'booking' : 'sale'));

            return $sale;
        });

        app(Tenancy::class)->run($tenant, fn () => self::afterPayment($sale->getRelation('firstPayment')));
        $sale = $sale->fresh(['plot', 'project', 'customer']);
        Notify::send('sale_status', [$customer->email], ['name' => $customer->name, 'plot_no' => $sale->plot->plot_no, 'project' => $sale->project->name, 'status' => $sale->plot->statusLabel()], $tenant->id);

        return $sale;
    }

    /**
     * Record an offline payment against a sale (cash, cheque, bank transfer, UPI) with receipt PDF emailed.
     * Payment always matches the sale's customer + tenant + plot.
     */
    public static function recordPayment(Sale $sale, array $data, int $userId, ?UploadedFile $proof = null, string $kind = 'sale'): Payment
    {
        $payment = DB::transaction(fn () => self::storePayment($sale, $data, $userId, $proof, $kind));
        self::afterPayment($payment);

        return $payment;
    }

    /** Database part of a payment (runs inside the caller's transaction). */
    private static function storePayment(Sale $sale, array $data, int $userId, ?UploadedFile $proof, string $kind): Payment
    {
        $tenant = Tenant::findOrFail($sale->tenant_id);
        $s = Sale::whereKey($sale->id)->lockForUpdate()->first();
        if ($s->status !== 'sale_init') {
            self::fail('amount', 'Payments can only be recorded while the sale is in progress (Sale Init).');
        }
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            self::fail('amount', 'Enter an amount greater than zero.');
        }
        if ($amount > (float) $s->due_amount + 0.001) {
            self::fail('amount', 'The amount is more than the balance due ('.Format::inr($s->due_amount).').');
        }
        $paidOn = Carbon::parse($data['paid_on']);
        if ($paidOn->isFuture()) {
            self::fail('paid_on', 'The payment date cannot be in the future.');
        }
        $txn = IdGenerator::next($tenant->id, 'income');
        $p = Payment::create([
            'tenant_id' => $tenant->id, 'sale_id' => $s->id, 'customer_id' => $s->customer_id, 'project_id' => $s->project_id, 'plot_id' => $s->plot_id,
            'kind' => $kind, 'transaction_no' => $txn, 'amount' => $amount, 'mode' => $data['mode'], 'reference_no' => $data['reference_no'] ?? null,
            'paid_on' => $paidOn, 'notes' => $data['notes'] ?? null, 'created_by' => $userId,
        ]);
        if ($proof) {
            $p->update(['proof_file_id' => FileStore::store($proof, 'payment-proof', $tenant->id, $p)->id]);
        }
        // allocate to instalments in order
        $left = $amount;
        foreach (Instalment::where('sale_id', $s->id)->orderBy('seq')->lockForUpdate()->get() as $inst) {
            if ($left <= 0) {
                break;
            }
            $bal = round((float) $inst->amount - (float) $inst->paid_amount, 2);
            if ($bal <= 0) {
                continue;
            }
            $take = min($bal, $left);
            $inst->paid_amount = round((float) $inst->paid_amount + $take, 2);
            $inst->status = $inst->paid_amount + 0.001 >= (float) $inst->amount ? 'paid' : 'partial';
            $inst->save();
            $left = round($left - $take, 2);
        }
        $paid = round((float) Payment::where('sale_id', $s->id)->sum('amount'), 2);
        $due = max(0, round((float) $s->net_price - $paid, 2));
        $s->update(['paid_amount' => $paid, 'due_amount' => $due]);
        Receipt::create(['tenant_id' => $tenant->id, 'payment_id' => $p->id, 'receipt_no' => $txn, 'issued_on' => today()]);
        if ($due <= 0.001) {
            // ROR only when nothing is due
            $s->update(['status' => 'ror', 'due_amount' => 0]);
            Plot::whereKey($s->plot_id)->first()->moveTo('ror', 'Fully paid');
        }

        return $p;
    }

    /** Receipt email and ROR notifications, after the transaction is committed. */
    private static function afterPayment(Payment $payment): void
    {
        self::emailReceipt($payment->fresh(['sale.plot', 'sale.project', 'customer', 'receipt']));
        $s = $payment->sale->fresh(['plot', 'project', 'customer']);
        if ($s->status === 'ror') {
            Notify::send('sale_status', [$s->customer->email], ['name' => $s->customer->name, 'plot_no' => $s->plot->plot_no, 'project' => $s->project->name, 'status' => 'Fully paid — ready for registration (ROR)'], $s->tenant_id);
            Notify::groups($s->tenant_id, ['Sales Team'], 'sale_status', ['name' => 'team', 'plot_no' => $s->plot->plot_no, 'project' => $s->project->name, 'status' => 'ROR — ready for registration']);
        }
    }

    public static function receiptPdf(Payment $payment): string
    {
        $payment->loadMissing(['sale.plot', 'sale.project', 'sale.payments', 'customer', 'receipt']);
        $tenant = Tenant::find($payment->tenant_id);

        return Pdf::render('pdf.receipt', ['payment' => $payment, 'sale' => $payment->sale, 'tenant' => $tenant, 'disclaimer' => $tenant->setting()->disclaimer_sale]);
    }

    private static function emailReceipt(Payment $payment): void
    {
        try {
            $dir = storage_path('app/private/receipts');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir.'/'.$payment->tenant_id.'-'.$payment->transaction_no.'.pdf';
            file_put_contents($path, self::receiptPdf($payment));
            $s = $payment->sale;
            Notify::send('payment_receipt', [$payment->customer->email], [
                'name' => $payment->customer->name, 'amount' => Format::inr($payment->amount), 'paid_on' => Format::date($payment->paid_on),
                'plot_no' => $s->plot->plot_no, 'project' => $s->project->name, 'receipt_no' => $payment->receipt->receipt_no,
                'paid' => Format::inr($s->paid_amount), 'due' => Format::inr($s->due_amount),
            ], $payment->tenant_id, [['path' => $path, 'name' => 'Receipt-'.$payment->receipt->receipt_no.'.pdf']]);
            $payment->receipt->update(['emailed_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Refund = paid − penalty (by days after the sale completion window). Needs Admin approval. */
    public static function refundQuote(Sale $sale, ?Carbon $on = null): array
    {
        $on ??= today();
        $daysLate = max(0, (int) $sale->window_end->diffInDays($on, false));
        $rule = RefundPenaltyRule::where('tenant_id', $sale->tenant_id)->where('from_days', '<=', $daysLate)
            ->where(fn ($q) => $q->whereNull('to_days')->orWhere('to_days', '>=', $daysLate))->orderByDesc('from_days')->first();
        $paid = (float) $sale->paid_amount;
        $penalty = 0.0;
        if ($rule) {
            $penalty = $rule->penalty_type === 'percent' ? round($paid * (float) $rule->value / 100, 2) : (float) $rule->value;
        }
        $penalty = min($penalty, $paid);

        return ['days_late' => $daysLate, 'rule' => $rule, 'paid' => $paid, 'penalty' => $penalty, 'refund' => round($paid - $penalty, 2)];
    }

    public static function requestRefund(Sale $sale, int $userId): Refund
    {
        return DB::transaction(function () use ($sale, $userId) {
            $s = Sale::whereKey($sale->id)->lockForUpdate()->first();
            if ($s->status !== 'sale_init') {
                self::fail('refund', 'A refund can only be requested for a sale in progress.');
            }
            if (! today()->gt($s->window_end)) {
                self::fail('refund', 'The sale completion window ends on '.Format::date($s->window_end).'. Refunds apply only after the customer misses it.');
            }
            $q = self::refundQuote($s);
            $r = Refund::create([
                'tenant_id' => $s->tenant_id, 'sale_id' => $s->id, 'customer_id' => $s->customer_id, 'plot_id' => $s->plot_id,
                'paid_amount' => $q['paid'], 'days_late' => $q['days_late'], 'penalty_amount' => $q['penalty'], 'refund_amount' => $q['refund'],
                'status' => 'pending', 'requested_by' => $userId,
            ]);
            $s->update(['status' => 'refund_pending']);

            return $r;
        });
    }

    public static function decideRefund(Refund $refund, bool $approve, ?string $notes, int $userId): void
    {
        DB::transaction(function () use ($refund, $approve, $notes, $userId) {
            $r = Refund::whereKey($refund->id)->lockForUpdate()->first();
            if ($r->status !== 'pending') {
                self::fail('decision', 'This refund has already been decided.');
            }
            $sale = Sale::whereKey($r->sale_id)->lockForUpdate()->first();
            if (! $approve) {
                $r->update(['status' => 'rejected', 'decided_by' => $userId, 'decided_at' => now(), 'decision_notes' => $notes]);
                $sale->update(['status' => 'sale_init']);

                return;
            }
            $expense = Expense::create([
                'tenant_id' => $r->tenant_id, 'transaction_no' => IdGenerator::next($r->tenant_id, 'expense'), 'project_id' => $sale->project_id,
                'expense_category_id' => ExpenseCategory::firstOrCreate(['tenant_id' => $r->tenant_id, 'name' => 'Refunds'])->id,
                'vendor' => $sale->customer->name, 'description' => "Refund for sale {$sale->sale_no} (plot {$sale->plot->plot_no}) — paid ".Format::inr($r->paid_amount).', penalty '.Format::inr($r->penalty_amount),
                'amount' => $r->refund_amount, 'spent_on' => today(), 'refund_id' => $r->id, 'created_by' => $userId,
            ]);
            $r->update(['status' => 'approved', 'decided_by' => $userId, 'decided_at' => now(), 'decision_notes' => $notes, 'expense_id' => $expense->id]);
            $sale->update(['status' => 'refunded', 'active_plot_lock' => null]);
            Plot::whereKey($sale->plot_id)->lockForUpdate()->first()->moveTo('available', 'Refund approved for sale '.$sale->sale_no);
        });
        $r = $refund->fresh(['sale.plot', 'sale.project', 'customer']);
        Notify::send('refund_decision', [$r->customer->email], [
            'name' => $r->customer->name, 'plot_no' => $r->sale->plot->plot_no, 'project' => $r->sale->project->name, 'status' => $approve ? 'approved' : 'not approved',
            'paid' => Format::inr($r->paid_amount), 'penalty' => Format::inr($r->penalty_amount), 'refund' => Format::inr($approve ? $r->refund_amount : 0),
        ], $r->tenant_id);
    }
}
