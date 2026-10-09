<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginService;
use App\Support\Passwords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/** Staff login: App users and tenant users — /workspace/login */
class StaffAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.staff-login');
    }

    public function login(Request $request)
    {
        $user = LoginService::attempt($request, 'web');
        if ($user->must_change_password) {
            return redirect()->route('staff.force-password');
        }

        return redirect()->intended($user->homeRoute());
    }

    public function logout(Request $request)
    {
        if ($u = Auth::guard('web')->user()) {
            AuditLogger::log('logout', $u, null, null, $u->tenant_id);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login')->with('ok', 'You have signed out.');
    }

    public function showForgot()
    {
        return view('auth.forgot', ['action' => route('password.email'), 'back' => route('staff.login')]);
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('users')->sendResetLink(['email' => strtolower($request->email)]);

        // Same answer whether or not the address exists (no account enumeration).
        return back()->with('ok', 'If that email belongs to an account, a reset link is on its way. The link is valid for 60 minutes.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email'), 'action' => route('password.update')]);
    }

    public function reset(Request $request)
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => Passwords::rules()], Passwords::messages());
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'must_change_password' => false, 'failed_logins' => 0, 'locked_until' => null])->setRememberToken(\Illuminate\Support\Str::random(60));
                $user->saveQuietly();
                AuditLogger::log('password_reset', $user, null, null, $user->tenant_id);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('staff.login')->with('ok', 'Password changed. Please sign in.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function showForceChange()
    {
        return view('auth.force-change', ['action' => route('staff.force-password.update')]);
    }

    public function forceChange(Request $request)
    {
        $request->validate(['password' => Passwords::rules()], Passwords::messages());
        $user = $request->user('web');
        if (\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Choose a new password, different from the generated one.']);
        }
        $user->forceFill(['password' => $request->password, 'must_change_password' => false])->saveQuietly();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        AuditLogger::log('password_changed', $user, null, null, $user->tenant_id);

        return redirect($user->homeRoute())->with('ok', 'Your new password is saved.');
    }
}
