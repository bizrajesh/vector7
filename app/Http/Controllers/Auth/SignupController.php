<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notify;
use App\Services\TenantProvisioner;
use App\Support\Format;
use App\Support\Passwords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** "Promoter workspace → Create workspace". No OTP: the workspace opens straight after sign-up. */
class SignupController extends Controller
{
    public function show()
    {
        return view('auth.signup');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:individual,organisation'],
            'name' => ['required', 'string', 'max:100'],
            'admin_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', function ($a, $v, $fail) {
                if (User::withoutGlobalScopes()->where('email', strtolower($v))->exists()) {
                    $fail('This email is already registered. Sign in instead.');
                }
            }],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'password' => Passwords::rules(),
            'terms' => ['accepted'],
        ], Passwords::messages() + ['mobile.regex' => 'Enter a 10-digit mobile number.', 'terms.accepted' => 'Please accept the terms to continue.']);

        [$tenant, $admin] = TenantProvisioner::create($data);
        AuditLogger::log('workspace_created', $tenant, null, ['code' => $tenant->code, 'name' => $tenant->name], $tenant->id);

        Notify::send('workspace_created', [$admin->email], [
            'name' => $admin->name,
            'tenant_name' => $tenant->name,
            'tenant_code' => $tenant->code,
            'trial_end' => Format::date($tenant->subscription->ends_on),
            'link' => route('staff.login'),
        ], $tenant->id);

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();

        return redirect()->route('ws.setup')->with('ok', "Your workspace is ready. Tenant ID: {$tenant->code}");
    }
}
