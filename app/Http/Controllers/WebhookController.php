<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Payment-gateway webhooks (subscription payments only). Signature is verified before anything else. */
class WebhookController extends Controller
{
    public function payment(Request $request, string $gateway)
    {
        abort_unless(in_array($gateway, ['razorpay', 'swipe'], true), 404);
        $body = $request->getContent();
        $sig = $gateway === 'razorpay' ? $request->header('X-Razorpay-Signature') : $request->header('X-Swipe-Signature');
        if (! SubscriptionService::verifyWebhook($gateway, $body, $sig)) {
            Log::warning("Rejected $gateway webhook: bad signature");

            return response()->json(['ok' => false], 400);
        }
        $payload = json_decode($body, true) ?: [];
        if ($gateway === 'razorpay') {
            if (($payload['event'] ?? '') !== 'payment_link.paid') {
                return response()->json(['ok' => true, 'ignored' => true]);
            }
            $link = $payload['payload']['payment_link']['entity'] ?? [];
            $paymentId = $payload['payload']['payment']['entity']['id'] ?? null;
            $invoice = SubscriptionInvoice::where('gateway_order_id', $link['id'] ?? '-')->orWhere('number', $link['reference_id'] ?? '-')->first();
        } else {
            $data = $payload['data'] ?? $payload;
            if (! in_array(strtolower((string) ($data['status'] ?? $payload['event'] ?? '')), ['paid', 'payment.success', 'payment_link.paid'], true)) {
                return response()->json(['ok' => true, 'ignored' => true]);
            }
            $paymentId = $data['payment_id'] ?? null;
            $invoice = SubscriptionInvoice::where('number', $data['reference_id'] ?? '-')->orWhere('gateway_order_id', $data['id'] ?? '-')->first();
        }
        if (! $invoice) {
            return response()->json(['ok' => false, 'error' => 'invoice not found'], 404);
        }
        SubscriptionService::markPaid($invoice, $gateway, $paymentId);

        return response()->json(['ok' => true]);
    }
}
