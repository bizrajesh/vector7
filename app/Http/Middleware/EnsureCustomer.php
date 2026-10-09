<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $c = Auth::guard('customer')->user();
        if (! $c || ! $c->is_active) {
            Auth::guard('customer')->logout();

            return redirect()->route('login')->with('error', 'Your account is disabled. Contact support.');
        }
        if ($c->must_change_password) {
            return redirect()->route('account.force-password');
        }

        return $next($request);
    }
}
