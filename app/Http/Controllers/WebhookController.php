<?php

namespace App\Http\Controllers;

use App\Models\ContactUnlock;
use App\Models\SubscriptionInvoice;
use App\Services\ContactCharges;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Payment-gateway webhooks: plan and top-up invoices, and buyers' contact-view payments. Signature is verified before anything else. */
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
            [$ref, $gid] = [$link['reference_id'] ?? '-', $link['id'] ?? '-'];
        } else {
            $data = $payload['data'] ?? $payload;
            if (! in_array(strtolower((string) ($data['status'] ?? $payload['event'] ?? '')), ['paid', 'payment.success', 'payment_link.paid'], true)) {
                return response()->json(['ok' => true, 'ignored' => true]);
            }
            $paymentId = $data['payment_id'] ?? null;
            [$ref, $gid] = [(string) ($data['reference_id'] ?? '-'), (string) ($data['id'] ?? '-')];
        }
        $invoice = SubscriptionInvoice::withoutGlobalScopes()->where(fn ($q) => $q->where('number', $ref)->orWhere('gateway_order_id', $gid))->first();
        if ($invoice) {
            SubscriptionService::markPaid($invoice, $gateway, $paymentId);

            return response()->json(['ok' => true]);
        }
        $unlock = ContactUnlock::where(fn ($q) => $q->where('number', $ref)->orWhere('gateway_order_id', $gid))->first();
        if (! $unlock) {
            return response()->json(['ok' => false, 'error' => 'invoice not found'], 404);
        }
        ContactCharges::markPaid($unlock, $gateway, $paymentId);

        return response()->json(['ok' => true]);
    }
}
