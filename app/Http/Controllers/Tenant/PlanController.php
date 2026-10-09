<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\UsageSnapshot;
use App\Services\PlanLimiter;
use App\Services\SubscriptionService;
use App\Support\Pdf;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** "My Plan & Usage": limit / used / left over, upgrade, invoices. */
class PlanController extends Controller
{
    public function show()
    {
        $tenant = app(Tenancy::class)->get();

        return view('ws.settings.plan', [
            'tenant' => $tenant,
            'sub' => $tenant->subscription,
            'usage' => PlanLimiter::usage($tenant),
            'monthly' => PlanLimiter::monthly($tenant),
            'plans' => Plan::where('is_active', true)->with('limits')->orderBy('sort')->get(),
            'invoices' => SubscriptionInvoice::with('plan')->latest('id')->take(24)->get(),
            'gateway' => SubscriptionService::gatewayName(),
            'history' => UsageSnapshot::where('tenant_id', $tenant->id)->orderBy('month')->take(12)->get(),
        ]);
    }

    public function upgrade(Request $request, Plan $plan)
    {
        abort_unless($plan->is_active, 404);
        $tenant = app(Tenancy::class)->get();
        $invoice = SubscriptionInvoice::where('plan_id', $plan->id)->where('status', 'due')->latest('id')->first()
            ?? SubscriptionService::createInvoice($tenant, $plan);
        if (SubscriptionService::gatewayName() === 'none') {
            return back()->with('ok', "Invoice {$invoice->number} created. vector7 will activate the {$plan->name} plan once payment is received.");
        }
        try {
            return redirect()->away(SubscriptionService::paymentLink($invoice->load('plan'), $tenant));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Return from the hosted payment page (Razorpay signs the query string). */
    public function verify(Request $request, SubscriptionInvoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return redirect()->route('ws.settings.plan')->with('ok', 'Payment received. Thank you!');
        }
        if (SubscriptionService::gatewayName() === 'razorpay' && $request->query('razorpay_payment_link_status') === 'paid'
            && $request->query('razorpay_payment_link_id') === $invoice->gateway_order_id
            && SubscriptionService::verifyRazorpayCallback($request->query())) {
            SubscriptionService::markPaid($invoice, 'razorpay', $request->query('razorpay_payment_id'));

            return redirect()->route('ws.settings.plan')->with('ok', 'Payment received. Your plan is active.');
        }

        return redirect()->route('ws.settings.plan')->with('warn', 'We have not received a payment confirmation yet. Your plan updates automatically once the gateway confirms it.');
    }

    public function invoice(SubscriptionInvoice $invoice)
    {
        return Pdf::download('pdf.invoice', ['invoice' => $invoice->load('plan', 'tenant')], $invoice->number.'.pdf');
    }
}
