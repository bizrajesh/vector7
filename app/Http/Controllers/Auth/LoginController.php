<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        // Only active accounts may sign in; a generic message avoids user enumeration.
        if (! Auth::attempt($credentials + ['status' => 'active'], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate(); // Session fixation defence.

        return redirect()->intended($request->user()->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
