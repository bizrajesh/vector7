<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\UsageSnapshot;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tenant subscriptions: invoices, payment through Razorpay or Swipe payment links (hosted pages,
 * so no third-party script runs on vector7), webhook verification, manual "mark paid", alerts.
 */
class SubscriptionService
{
    public const GST_PCT = 18;

    public static function createInvoice(Tenant $tenant, Plan $plan): SubscriptionInvoice
    {
        $current = $tenant->subscription;
        $start = $current && $current->status === 'active' && $current->ends_on->isFuture() && $current->plan_id === $plan->id ? $current->ends_on->copy()->addDay() : today();
        $end = $plan->billing_cycle === 'yearly' ? $start->copy()->addYear()->subDay() : $start->copy()->addMonth()->subDay();
        $amount = (float) $plan->price;
        $tax = round($amount * self::GST_PCT / 100, 2);

        return SubscriptionInvoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $current?->id,
            'plan_id' => $plan->id,
            'number' => self::invoiceNumber(),
            'amount' => $amount,
            'tax' => $tax,
            'total' => $amount + $tax,
            'status' => 'due',
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }

    private static function invoiceNumber(): string
    {
        $prefix = 'V7-'.now()->format('Ym').'-';
        $n = SubscriptionInvoice::where('number', 'like', $prefix.'%')->count() + 1;
        do {
            $num = $prefix.str_pad((string) $n++, 4, '0', STR_PAD_LEFT);
        } while (SubscriptionInvoice::where('number', $num)->exists());

        return $num;
    }

    /** Mark an invoice paid and activate/extend the subscription. Idempotent. */
    public static function markPaid(SubscriptionInvoice $invoice, string $method, ?string $paymentId = null, ?User $by = null): void
    {
        DB::transaction(function () use ($invoice, $method, $paymentId, $by) {
            $invoice = SubscriptionInvoice::withoutGlobalScopes()->lockForUpdate()->find($invoice->id);
            if ($invoice->status === 'paid') {
                return;
            }
            $invoice->update(['status' => 'paid', 'method' => $method, 'gateway_payment_id' => $paymentId, 'paid_at' => now(), 'marked_paid_by' => $by?->id]);
            $sub = Subscription::withoutGlobalScopes()->where('tenant_id', $invoice->tenant_id)->latest('id')->first();
            $attrs = ['plan_id' => $invoice->plan_id, 'status' => 'active', 'starts_on' => $invoice->period_start, 'ends_on' => $invoice->period_end, 'alerts_sent' => []];
            if ($sub && $sub->plan_id === $invoice->plan_id && $sub->status === 'active') {
                $attrs['starts_on'] = $sub->starts_on;
            }
            $sub ? $sub->update($attrs) : Subscription::create(['tenant_id' => $invoice->tenant_id] + $attrs);
            AuditLogger::log('subscription_paid', $invoice, null, ['invoice' => $invoice->number, 'method' => $method], $invoice->tenant_id);
        });
    }

    public static function gatewayName(): string
    {
        return (string) AppSettings::get('payment.gateway', 'none');
    }

    /** Create a hosted payment link and return its URL. */
    public static function paymentLink(SubscriptionInvoice $invoice, Tenant $tenant): string
    {
        $gateway = self::gatewayName();
        $callback = route('ws.settings.invoice.verify', $invoice);
        if ($gateway === 'razorpay') {
            $r = Http::withBasicAuth((string) AppSettings::get('payment.razorpay_key_id'), (string) AppSettings::get('payment.razorpay_key_secret'))
                ->acceptJson()->timeout(30)->post('https://api.razorpay.com/v1/payment_links', [
                    'amount' => (int) round($invoice->total * 100),
                    'currency' => 'INR',
                    'reference_id' => $invoice->number,
                    'description' => 'vector7 '.$invoice->plan->name.' plan '.Format::date($invoice->period_start).' – '.Format::date($invoice->period_end),
                    'customer' => ['name' => $tenant->name, 'email' => $tenant->email, 'contact' => $tenant->contact],
                    'notify' => ['email' => true],
                    'callback_url' => $callback,
                    'callback_method' => 'get',
                ]);
            if (! $r->successful()) {
                throw new RuntimeException('Razorpay: '.$r->json('error.description', 'could not create the payment link'));
            }
            $invoice->update(['gateway_order_id' => $r->json('id'), 'method' => 'razorpay']);

            return (string) $r->json('short_url');
        }
        if ($gateway === 'swipe') {
            $base = rtrim((string) AppSettings::get('payment.swipe_api_base', 'https://app.getswipe.in/api/partner/v2'), '/');
            $r = Http::withToken((string) AppSettings::get('payment.swipe_api_key'))->acceptJson()->timeout(30)->post($base.'/payment_links', [
                'amount' => round($invoice->total, 2),
                'currency' => 'INR',
                'reference_id' => $invoice->number,
                'description' => 'vector7 '.$invoice->plan->name.' plan',
                'customer_name' => $tenant->name,
                'customer_email' => $tenant->email,
                'customer_phone' => $tenant->contact,
                'callback_url' => $callback,
            ]);
            if (! $r->successful()) {
                throw new RuntimeException('Swipe: could not create the payment link (HTTP '.$r->status().').');
            }
            $invoice->update(['gateway_order_id' => (string) ($r->json('data.id') ?? $r->json('id')), 'method' => 'swipe']);

            return (string) ($r->json('data.short_url') ?? $r->json('data.url') ?? $r->json('short_url') ?? $r->json('url'));
        }
        throw new RuntimeException('Online payment is not configured. Contact vector7 to activate your plan.');
    }

    /** Razorpay callback signature: HMAC-SHA256(plink_id|reference_id|status|payment_id, key_secret). */
    public static function verifyRazorpayCallback(array $q): bool
    {
        $payload = ($q['razorpay_payment_link_id'] ?? '').'|'.($q['razorpay_payment_link_reference_id'] ?? '').'|'.($q['razorpay_payment_link_status'] ?? '').'|'.($q['razorpay_payment_id'] ?? '');
        $expected = hash_hmac('sha256', $payload, (string) AppSettings::get('payment.razorpay_key_secret'));

        return isset($q['razorpay_signature']) && hash_equals($expected, (string) $q['razorpay_signature']);
    }

    /** Webhook signature: HMAC-SHA256 of the raw body with the webhook secret. */
    public static function verifyWebhook(string $gateway, string $body, ?string $signature): bool
    {
        $secret = (string) AppSettings::get($gateway === 'razorpay' ? 'payment.razorpay_webhook_secret' : 'payment.swipe_webhook_secret');
        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    /** Daily: expire subscriptions, send 7-day and 1-day reminders, usage alerts at 80 % / 100 %, monthly snapshot. */
    public static function dailyRun(): void
    {
        foreach (Tenant::with('subscription.plan')->get() as $tenant) {
            $sub = $tenant->subscription;
            if (! $sub) {
                continue;
            }
            $admins = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)
                ->whereHas('role', fn ($q) => $q->withoutGlobalScopes()->where('base_role', 'tenant_admin'))->get();
            $sent = $sub->alerts_sent ?? [];
            $link = route('ws.settings.plan');
            $mail = function (string $key, string $template, array $data) use (&$sent, $admins, $tenant) {
                if (isset($sent[$key])) {
                    return;
                }
                foreach ($admins as $a) {
                    Notify::send($template, [$a->email], ['name' => $a->name] + $data, $tenant->id);
                }
                $sent[$key] = now()->toDateString();
            };
            if (in_array($sub->status, ['trial', 'active', 'past_due'], true) && $sub->ends_on->lt(today())) {
                $sub->update(['status' => $sub->status === 'active' ? 'past_due' : 'expired']);
                if ($sub->status === 'past_due' && $sub->ends_on->lt(today()->subDays(7))) {
                    $sub->update(['status' => 'expired']);
                }
                $mail('expired_'.$sub->ends_on->toDateString(), 'subscription_alert', ['message' => 'Your subscription has ended. Renew to continue using vector7.', 'plan' => $sub->plan->name, 'ends_on' => Format::date($sub->ends_on), 'link' => $link]);
            } elseif ($sub->isUsable()) {
                $days = $sub->daysLeft();
                foreach ([7, 1] as $d) {
                    if ($days <= $d) {
                        $mail("ends_{$d}_".$sub->ends_on->toDateString(), 'subscription_alert', ['message' => "Your subscription ends in $days day(s).", 'plan' => $sub->plan->name, 'ends_on' => Format::date($sub->ends_on), 'link' => $link]);
                    }
                }
            }
            foreach (PlanLimiter::usage($tenant) as $key => $row) {
                if ($row['unlimited'] || $row['limit'] <= 0) {
                    continue;
                }
                foreach ([100, 80] as $level) {
                    if ($row['pct'] >= $level) {
                        $mail("usage_{$key}_{$level}_".now()->format('Ym'), 'usage_alert', ['pct' => $row['pct'], 'limit' => $row['label'], 'used' => $row['display']['used'], 'max' => $row['display']['limit'], 'link' => $link]);
                        break;
                    }
                }
            }
            $sub->forceFill(['alerts_sent' => $sent])->saveQuietly();
            self::snapshot($tenant);
        }
    }

    public static function snapshot(Tenant $tenant): void
    {
        $m = PlanLimiter::monthly($tenant);
        $users = \App\Models\User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        UsageSnapshot::updateOrCreate(['tenant_id' => $tenant->id, 'month' => now()->format('Y-m')], [
            'storage_bytes' => $tenant->storage_used_bytes,
            'projects' => PlanLimiter::used($tenant, 'projects'),
            'users' => $users,
            'ai_credits' => PlanLimiter::used($tenant, 'ai_credits'),
            'plots_launched' => $m['plots_launched'],
            'bookings' => $m['bookings'],
            'sales' => $m['sales'],
        ]);
    }
}
