<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSuperAdmin()) {
            SecurityLog::warning('platform_access_denied', ['user_id' => $request->user()?->id, 'path' => $request->path()]);
            abort(404);
        }

        return $next($request);
    }
}
