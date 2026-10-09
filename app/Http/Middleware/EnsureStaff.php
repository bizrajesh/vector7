<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Active staff user; forces "change password at next login" before anything else. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user || ! $user->is_active || ($user->tenant_id && $user->tenant?->status !== 'active')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('staff.login')->with('error', 'Your account is disabled. Contact your administrator.');
        }
        if ($user->must_change_password) {
            return redirect()->route('staff.force-password');
        }

        return $next($request);
    }
}
