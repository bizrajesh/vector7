<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** REGISTRATION_OPEN=false closes self sign-up for buyers, property listers and promoters (e.g. while testing live). */
class EnsureRegistrationOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('vector7.registration_open')) {
            return $next($request);
        }
        if (! $request->isMethod('GET')) {
            abort(403, 'New accounts cannot be created right now.');
        }

        return response()->view('auth.signup-closed', [], 403);
    }
}
