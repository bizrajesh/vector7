<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Services\Payments\PaymentGateway;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    public function show(TenantContext $context): View
    {
        $tenant = $context->tenant()->load(['plan', 'subscription', 'invoices' => fn ($q) => $q->latest()->limit(12)]);

        return view('app.billing', ['tenant' => $tenant]);
    }

    public function pay(Request $request, TenantContext $context, PaymentGateway $gateway): RedirectResponse
    {
        $tenant = $context->tenant();
        $subscription = $tenant->subscription;
        abort_unless($subscription && $gateway->isConfigured(), 422, 'Online payment is not configured. Contact support.');

        $invoice = SubscriptionInvoice::query()->where('tenant_id', $tenant->id)->where('status', 'due')->latest()->first();
        if (! $invoice) {
            $amount = (float) $tenant->plan->price($subscription->cycle);
            abort_unless($amount > 0, 422, 'This plan has no price set yet.');
            $gst = round($amount * 0.18, 2);
            $invoice = SubscriptionInvoice::create([
                'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id,
                'invoice_no' => 'XAX-'.now()->format('Ym').'-'.str_pad((string) (SubscriptionInvoice::max('id') + 1), 5, '0', STR_PAD_LEFT),
                'amount' => $amount, 'gst_amount' => $gst, 'total' => $amount + $gst, 'status' => 'due',
            ]);
        }

        try {
            return redirect()->away($gateway->checkoutUrl($invoice));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Could not start the payment. Please try again in a few minutes.');
        }
    }
}
