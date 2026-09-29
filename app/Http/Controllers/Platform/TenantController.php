<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ImpersonationSession;
use App\Models\Layout;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(SubscriptionStatus::class)]]);

        $tenants = Tenant::query()->with(['plan', 'subscription'])->withCount('users')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($term, '%_').'%')->orWhere('email', 'like', '%'.addcslashes($term, '%_').'%')))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(25)->withQueryString();

        return view('platform.tenants.index', ['tenants' => $tenants]);
    }

    public function show(Tenant $tenant): View
    {
        return view('platform.tenants.show', [
            'tenant' => $tenant->load(['plan', 'subscription', 'invoices' => fn ($q) => $q->latest()->limit(12)]),
            'users' => User::query()->where('tenant_id', $tenant->id)->orderBy('role')->get(),
            'usage' => [
                'layouts' => Layout::acrossTenants()->where('tenant_id', $tenant->id)->count(),
                'plots' => Plot::acrossTenants()->where('tenant_id', $tenant->id)->count(),
            ],
            'plans' => Plan::query()->orderBy('sort_order')->get(),
            'audit' => AuditLog::query()->where('tenant_id', $tenant->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function status(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])], 'reason' => ['required', 'string', 'max:255']]);

        $tenant->status = SubscriptionStatus::from($data['status']);
        $tenant->save();
        $tenant->subscription?->update(['status' => $data['status']]);
        $this->audit->event('tenant.status', $data, $tenant->id);

        return $this->done('Tenant status updated.');
    }

    public function extendTrial(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:60']]);
        $subscription = $tenant->subscription;
        abort_unless($subscription, 422);

        $subscription->trial_ends_at = ($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture() ? $subscription->trial_ends_at : now())->addDays($data['days']);
        $subscription->status = SubscriptionStatus::Trial;
        $subscription->save();
        $tenant->status = SubscriptionStatus::Trial;
        $tenant->save();
        $this->audit->event('tenant.trial_extended', $data, $tenant->id);

        return $this->done('Trial extended.');
    }

    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $tenant->plan_id = $data['plan_id'];
        $tenant->save();
        $tenant->subscription?->update(['plan_id' => $data['plan_id']]);
        $this->audit->event('tenant.plan_changed', $data, $tenant->id);

        return $this->done('Plan changed.');
    }

    /** Time-boxed, reason-required, fully logged impersonation of the tenant Admin. */
    public function impersonate(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:255']]);
        $target = User::query()->where('tenant_id', $tenant->id)->where('role', Role::Admin)->where('status', 'active')->firstOrFail();
        $admin = $request->user();

        $session = ImpersonationSession::create([
            'super_admin_id' => $admin->id, 'tenant_id' => $tenant->id, 'target_user_id' => $target->id,
            'reason' => $data['reason'], 'started_at' => now(), 'expires_at' => now()->addMinutes(30),
        ]);
        $this->audit->event('impersonation.started', ['target_user_id' => $target->id, 'reason' => $data['reason']], $tenant->id);
        SecurityLog::info('impersonation_started', ['super_admin_id' => $admin->id, 'target_user_id' => $target->id]);

        Auth::login($target);
        $request->session()->regenerate();
        $request->session()->put(['impersonation_id' => $session->id, 'impersonator_id' => $admin->id]);

        return redirect()->route('app.dashboard');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $sessionId = $request->session()->pull('impersonation_id');
        $adminId = $request->session()->pull('impersonator_id');
        abort_unless($sessionId && $adminId, 403);

        ImpersonationSession::whereKey($sessionId)->update(['ended_at' => now()]);
        SecurityLog::info('impersonation_stopped', ['super_admin_id' => $adminId]);

        Auth::loginUsingId($adminId);
        $request->session()->regenerate();

        return redirect()->route('platform.dashboard')->with('status', 'Impersonation ended.');
    }
}
