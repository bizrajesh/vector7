<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginService;
use App\Services\Notify;
use App\Services\TenantProvisioner;
use App\Support\Format;
use App\Support\Passwords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Property Lister: an independent owner or agent who only posts and tracks listings.
 * Signs up and signs in from the marketplace (/list-property), gets their own small workspace and lister plans.
 */
class ListerAuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.lister-register', ['plans' => Plan::for('lister')->where('is_active', true)->with('limits')->orderBy('sort')->get()]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', function ($a, $v, $fail) {
                if (User::withoutGlobalScopes()->where('email', strtolower($v))->exists()) {
                    $fail(__('This email is already registered. Sign in instead.'));
                }
            }],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'password' => Passwords::rules(),
            'terms' => ['accepted'],
        ], Passwords::messages() + ['mobile.regex' => __('Enter a 10-digit mobile number.'), 'terms.accepted' => __('Please accept the terms to continue.')]);

        $data['name'] = ($data['name'] ?? null) ?: $data['admin_name'];
        [$tenant, $user] = TenantProvisioner::create($data + ['type' => 'lister']);
        AuditLogger::log('lister_registered', $tenant, null, ['code' => $tenant->code, 'name' => $tenant->name], $tenant->id);
        Notify::send('workspace_created', [$user->email], [
            'name' => $user->name, 'tenant_name' => $tenant->name, 'tenant_code' => $tenant->code,
            'trial_end' => Format::date($tenant->subscription->ends_on), 'link' => route('lister.login'),
        ], $tenant->id);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        \App\Services\LoginEvents::record($request, 'lister', $user, 'success', 'registered');

        return redirect()->route('ws.listings.create')->with('ok', __('Welcome! Post your first property — it goes live as soon as you publish it.'));
    }

    public function showLogin()
    {
        return view('auth.lister-login');
    }

    public function login(Request $request)
    {
        $user = LoginService::attempt($request, 'web', fn ($u) => $u instanceof User && $u->isLister() ? null
            : __('This sign-in page is for property listers. Promoters and their teams use the promoter workspace sign-in.'), 'lister');
        if ($user->must_change_password) {
            return redirect()->route('staff.force-password');
        }

        return redirect()->intended(route('ws.dashboard'));
    }
}
