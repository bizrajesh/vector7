<?php

namespace App\Http\Middleware;

use App\Support\Signups;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * signup:buyer | signup:lister | signup:promoter — the sign-up page answers only when the App Admin has
 * switched that kind of sign-up on (App → Settings → Sign-ups). Otherwise a friendly "not open" page (403).
 */
class EnsureRegistrationOpen
{
    public function handle(Request $request, Closure $next, string $kind = 'buyer'): Response
    {
        if (Signups::open($kind)) {
            return $next($request);
        }
        if (! $request->isMethod('GET')) {
            abort(403, 'New accounts cannot be created right now.');
        }

        return response()->view('auth.signup-closed', ['kind' => $kind], 403);
    }
}
