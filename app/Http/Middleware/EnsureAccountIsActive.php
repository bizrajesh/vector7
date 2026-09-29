<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive() || ($user->tenant_id && ! $user->tenant)) {
            SecurityLog::warning('inactive_account_blocked', ['user_id' => $user?->id]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account is not active. Contact your administrator.']);
        }

        if (config('vector7.require_email_verification') && ! $user->hasVerifiedEmail() && ! $user->isSuperAdmin()) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
