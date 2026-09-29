<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\TenantProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Tenant self-registration (section 3A.2). The registering user becomes the tenant Admin.
 */
class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        $plans = Plan::query()->active()->get();

        return view('auth.register', [
            'plans' => $plans,
            'selectedPlan' => $plans->firstWhere('code', $request->query('plan')) ?? $plans->firstWhere('code', 'growth') ?? $plans->first(),
            'cycle' => $request->query('cycle') === 'monthly' ? 'monthly' : 'yearly',
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::exists('plans', 'code')->where('status', 'active')],
            'cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'business_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $plan = Plan::query()->where('code', $data['plan'])->firstOrFail();
        $admin = $provisioner->register($data, $plan, $data['cycle']);

        event(new Registered($admin));
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route(config('vector7.require_email_verification') ? 'verification.notice' : 'app.dashboard')
            ->with('status', 'Welcome to Vector7! Your '.$plan->name.' trial has started.');
    }
}
