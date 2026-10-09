<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\StorageFile;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\UsageSnapshot;
use App\Services\AuditLogger;
use App\Services\PlanLimiter;
use App\Services\SubscriptionService;
use App\Support\Excel;
use App\Support\Format;
use App\Support\Pdf;
use Illuminate\Http\Request;

/** Tenant Subscription & Usage screen: one row per tenant, limit bars, near-limit filter, history, export. */
class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::with('subscription.plan.limits')->orderBy('name')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->get();
        $rows = $tenants->map(function (Tenant $t) {
            $usage = PlanLimiter::usage($t);
            $max = collect($usage)->reject(fn ($u) => $u['unlimited'] || $u['limit'] <= 0)->max('pct') ?? 0;

            return ['tenant' => $t, 'sub' => $t->subscription, 'usage' => $usage, 'max' => $max];
        });
        if ($p = $request->query('plan')) {
            $rows = $rows->filter(fn ($r) => (string) $r['sub']?->plan_id === (string) $p);
        }
        if ($s = $request->query('status')) {
            $rows = $rows->filter(fn ($r) => $r['sub']?->status === $s);
        }
        if ($request->query('near') === '1') {
            $rows = $rows->filter(fn ($r) => $r['max'] >= 80);
        }
        if ($request->query('export') === 'xlsx') {
            $keys = array_keys(PlanLimiter::LIMIT_LABELS);

            return Excel::download('tenant-usage-'.now()->format('Ymd').'.xlsx',
                array_merge(['Tenant ID', 'Tenant', 'Plan', 'Status', 'Start', 'End', 'Days left'], array_merge(...array_map(fn ($k) => [PlanLimiter::LIMIT_LABELS[$k].' limit', 'used', 'left', '% used'], $keys))),
                $rows->map(fn ($r) => array_merge(
                    [$r['tenant']->code, $r['tenant']->name, $r['sub']?->plan->name, $r['sub']?->status, $r['sub']?->starts_on, $r['sub']?->ends_on, $r['sub']?->daysLeft()],
                    array_merge(...array_map(fn ($k) => [$r['usage'][$k]['display']['limit'], $r['usage'][$k]['display']['used'], $r['usage'][$k]['display']['left'], $r['usage'][$k]['pct']], $keys))
                ))->values()->all(), 'Tenant subscriptions & usage');
        }

        return view('app.subscriptions.index', ['rows' => $rows->values(), 'plans' => Plan::orderBy('sort')->get()]);
    }

    public function show(Tenant $tenant)
    {
        return view('app.subscriptions.show', [
            'tenant' => $tenant->load('subscription.plan'),
            'usage' => PlanLimiter::usage($tenant),
            'monthly' => PlanLimiter::monthly($tenant),
            'history' => UsageSnapshot::where('tenant_id', $tenant->id)->orderBy('month')->take(24)->get(),
            'largest' => StorageFile::where('tenant_id', $tenant->id)->orderByDesc('size')->take(15)->get(),
            'invoices' => SubscriptionInvoice::where('tenant_id', $tenant->id)->with('plan')->latest('id')->get(),
            'plans' => Plan::orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:trial,active,past_due,expired,cancelled',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
        ]);
        $sub = $tenant->subscription;
        $sub ? $sub->update($data + ['alerts_sent' => []]) : $tenant->subscriptions()->create($data);

        return back()->with('ok', 'Subscription updated.');
    }

    public function status(Request $request, Tenant $tenant)
    {
        $request->validate(['status' => 'required|in:active,suspended']);
        $tenant->update(['status' => $request->status]);
        AuditLogger::log('tenant_'.$request->status, $tenant, null, ['code' => $tenant->code], $tenant->id);

        return back()->with('ok', 'Workspace '.($request->status === 'active' ? 'activated' : 'suspended').'.');
    }

    /** Manual payment when no gateway is configured (or paid offline). */
    public function markPaid(Request $request, SubscriptionInvoice $invoice)
    {
        SubscriptionService::markPaid($invoice, 'manual', $request->input('reference'), $request->user());

        return back()->with('ok', "Invoice {$invoice->number} marked paid; subscription active until ".Format::date($invoice->fresh()->period_end).'.');
    }

    public function invoice(SubscriptionInvoice $invoice)
    {
        return Pdf::download('pdf.invoice', ['invoice' => $invoice->load('plan', 'tenant')], $invoice->number.'.pdf');
    }
}
