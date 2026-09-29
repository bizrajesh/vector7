<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookEvent;
use App\Models\SubscriptionInvoice;
use App\Services\Payments\PaymentGateway;
use App\Support\SecurityLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Razorpay webhook: HMAC-verified (OWASP A08) and idempotent (replayed events are ignored).
 */
class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $payload = $request->getContent();

        if (! $gateway->verifyWebhook($payload, $request->header('X-Razorpay-Signature'))) {
            SecurityLog::warning('webhook_signature_invalid', ['gateway' => 'razorpay']);

            return response()->json(['ok' => false], 401);
        }

        $eventId = (string) $request->header('X-Razorpay-Event-Id', hash('sha256', $payload));
        $data = json_decode($payload, true, 32);
        $type = (string) ($data['event'] ?? 'unknown');

        try {
            $event = PaymentWebhookEvent::create(['gateway' => 'razorpay', 'event_id' => $eventId, 'type' => $type, 'payload' => $data]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        if ($type === 'payment_link.paid') {
            $invoiceNo = (string) data_get($data, 'payload.payment_link.entity.reference_id');
            $paidPaise = (int) data_get($data, 'payload.payment_link.entity.amount_paid');

            DB::transaction(function () use ($invoiceNo, $paidPaise, $data) {
                $invoice = SubscriptionInvoice::query()->where('invoice_no', $invoiceNo)->lockForUpdate()->first();
                if (! $invoice || $invoice->status === 'paid' || $paidPaise < (int) round((float) $invoice->total * 100)) {
                    SecurityLog::warning('webhook_invoice_mismatch', ['invoice' => $invoiceNo]);

                    return;
                }

                $invoice->update(['status' => 'paid', 'paid_at' => now(), 'gateway_payment_id' => (string) data_get($data, 'payload.payment.entity.id')]);

                $subscription = $invoice->tenant->subscription;
                $start = $subscription->current_period_end && $subscription->current_period_end->isFuture() ? $subscription->current_period_end : now();
                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'current_period_start' => $start,
                    'current_period_end' => $subscription->cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth(),
                    'grace_ends_at' => null,
                    'gateway' => 'razorpay',
                ]);
                $invoice->tenant->forceFill(['status' => SubscriptionStatus::Active])->save();
            });
        }

        $event->update(['processed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
