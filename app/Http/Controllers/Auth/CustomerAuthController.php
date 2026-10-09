<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditLogger;
use App\Services\LoginService;
use App\Support\Passwords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Marketplace customers — /login, /register (email + password, no OTP). */
class CustomerAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.customer-login');
    }

    public function login(Request $request)
    {
        $c = LoginService::attempt($request, 'customer');
        if ($c->must_change_password) {
            return redirect()->route('account.force-password');
        }

        return redirect()->intended(route('account.dashboard'));
    }

    public function showRegister()
    {
        return view('auth.customer-register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'password' => Passwords::rules(),
            'terms' => ['accepted'],
        ], Passwords::messages() + ['mobile.regex' => 'Enter a 10-digit mobile number.']);
        $c = Customer::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'mobile' => $data['mobile'],
            'password' => $data['password'],
        ]);
        Auth::guard('customer')->login($c);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))->with('ok', 'Welcome to vector7! Your account is ready.');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgot()
    {
        return view('auth.forgot', ['action' => route('customer.password.email'), 'back' => route('login')]);
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('customers')->sendResetLink(['email' => strtolower($request->email)]);

        return back()->with('ok', 'If that email belongs to an account, a reset link is on its way. The link is valid for 60 minutes.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email'), 'action' => route('customer.password.update')]);
    }

    public function reset(Request $request)
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => Passwords::rules()], Passwords::messages());
        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $c, string $password) {
                $c->forceFill(['password' => $password, 'must_change_password' => false, 'failed_logins' => 0, 'locked_until' => null])->setRememberToken(Str::random(60));
                $c->saveQuietly();
                AuditLogger::log('password_reset', $c);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('ok', 'Password changed. Please sign in.')
            : back()->withErrors(['email' => __($status)]);
    }

    public function showForceChange()
    {
        return view('auth.force-change', ['action' => route('account.force-password.update')]);
    }

    public function forceChange(Request $request)
    {
        $request->validate(['password' => Passwords::rules()], Passwords::messages());
        $c = $request->user('customer');
        if (Hash::check($request->password, $c->password)) {
            return back()->withErrors(['password' => 'Choose a new password, different from the generated one.']);
        }
        $c->forceFill(['password' => $request->password, 'must_change_password' => false])->saveQuietly();
        Auth::guard('customer')->login($c);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('ok', 'Your new password is saved.');
    }
}
