<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Hosted payment links (Razorpay / Swipe) for anything vector7 bills: plan invoices, top-ups and buyers' contact views.
 * Hosted pages keep third-party scripts off vector7 (strict CSP).
 */
class PaymentLinks
{
    public static function gateway(): string
    {
        return (string) AppSettings::get('payment.gateway', 'none');
    }

    public static function enabled(): bool
    {
        return in_array(self::gateway(), ['razorpay', 'swipe'], true);
    }

    /**
     * @param  array{name?:string, email?:string, phone?:string}  $payer
     * @return array{id:string, url:string, gateway:string}
     */
    public static function create(string $reference, float $total, string $description, array $payer, string $callback): array
    {
        $gateway = self::gateway();
        if ($gateway === 'razorpay') {
            $r = Http::withBasicAuth((string) AppSettings::get('payment.razorpay_key_id'), (string) AppSettings::get('payment.razorpay_key_secret'))
                ->acceptJson()->timeout(30)->post('https://api.razorpay.com/v1/payment_links', [
                    'amount' => (int) round($total * 100),
                    'currency' => 'INR',
                    'reference_id' => $reference,
                    'description' => mb_substr($description, 0, 240),
                    'customer' => array_filter(['name' => $payer['name'] ?? null, 'email' => $payer['email'] ?? null, 'contact' => $payer['phone'] ?? null]),
                    'notify' => ['email' => ! empty($payer['email'])],
                    'callback_url' => $callback,
                    'callback_method' => 'get',
                ]);
            if (! $r->successful()) {
                throw new RuntimeException('Razorpay: '.$r->json('error.description', 'could not create the payment link'));
            }

            return ['id' => (string) $r->json('id'), 'url' => (string) $r->json('short_url'), 'gateway' => 'razorpay'];
        }
        if ($gateway === 'swipe') {
            $base = rtrim((string) AppSettings::get('payment.swipe_api_base', 'https://app.getswipe.in/api/partner/v2'), '/');
            $r = Http::withToken((string) AppSettings::get('payment.swipe_api_key'))->acceptJson()->timeout(30)->post($base.'/payment_links', [
                'amount' => round($total, 2),
                'currency' => 'INR',
                'reference_id' => $reference,
                'description' => mb_substr($description, 0, 240),
                'customer_name' => $payer['name'] ?? null,
                'customer_email' => $payer['email'] ?? null,
                'customer_phone' => $payer['phone'] ?? null,
                'callback_url' => $callback,
            ]);
            if (! $r->successful()) {
                throw new RuntimeException('Swipe: could not create the payment link (HTTP '.$r->status().').');
            }

            return ['id' => (string) ($r->json('data.id') ?? $r->json('id')), 'url' => (string) ($r->json('data.short_url') ?? $r->json('data.url') ?? $r->json('short_url') ?? $r->json('url')), 'gateway' => 'swipe'];
        }
        throw new RuntimeException('Online payment is not configured.');
    }

    /** Razorpay callback signature: HMAC-SHA256(plink_id|reference_id|status|payment_id, key_secret). */
    public static function verifyRazorpayCallback(array $q): bool
    {
        $payload = ($q['razorpay_payment_link_id'] ?? '').'|'.($q['razorpay_payment_link_reference_id'] ?? '').'|'.($q['razorpay_payment_link_status'] ?? '').'|'.($q['razorpay_payment_id'] ?? '');
        $expected = hash_hmac('sha256', $payload, (string) AppSettings::get('payment.razorpay_key_secret'));

        return isset($q['razorpay_signature']) && hash_equals($expected, (string) $q['razorpay_signature']);
    }

    /** True when the query string is a signed "paid" Razorpay callback for this payment link. */
    public static function paidCallback(array $q, ?string $linkId): bool
    {
        return self::gateway() === 'razorpay' && ($q['razorpay_payment_link_status'] ?? null) === 'paid'
            && $linkId && ($q['razorpay_payment_link_id'] ?? null) === $linkId && self::verifyRazorpayCallback($q);
    }

    /** Split a GST-inclusive price into [amount, tax]. */
    public static function splitInclusive(float $total, float $gstPct = 18): array
    {
        $amount = round($total / (1 + $gstPct / 100), 2);

        return [$amount, round($total - $amount, 2)];
    }
}
