<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('web')?->isAppUser(), 403, 'App workspace only.');

        return $next($request);
    }
}
