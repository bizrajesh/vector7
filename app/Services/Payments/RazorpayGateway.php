<?php

namespace App\Services\Payments;

use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay Payment Links. The browser is redirected to Razorpay's hosted page,
 * so no third-party script runs on Vector7 pages and no card data reaches us.
 */
class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'))
            && filled(config('services.razorpay.webhook_secret'));
    }

    public function checkoutUrl(SubscriptionInvoice $invoice): string
    {
        $tenant = $invoice->tenant;

        $response = Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))
            ->acceptJson()->timeout(15)->retry(2, 500)
            ->post(self::API.'/payment_links', [
                'amount' => (int) round((float) $invoice->total * 100),
                'currency' => 'INR',
                'reference_id' => $invoice->invoice_no,
                'description' => 'Vector7 subscription '.$invoice->invoice_no,
                'customer' => ['name' => $tenant->name, 'email' => $tenant->email, 'contact' => $tenant->phone],
                'notify' => ['email' => true, 'sms' => false],
                'callback_url' => route('billing.show'),
                'callback_method' => 'get',
                'notes' => ['invoice_no' => $invoice->invoice_no],
            ]);

        if (! $response->successful() || ! is_string($response->json('short_url'))) {
            throw new RuntimeException('Payment gateway error: '.$response->status());
        }

        return $response->json('short_url');
    }

    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        if (! $signature || ! filled(config('services.razorpay.webhook_secret'))) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, (string) config('services.razorpay.webhook_secret'));

        return hash_equals($expected, $signature);
    }
}
