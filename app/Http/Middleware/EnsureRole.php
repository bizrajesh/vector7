<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:admin,sales') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            SecurityLog::warning('role_denied', ['user_id' => $user?->id, 'need' => $roles, 'path' => $request->path()]);
            abort(403);
        }

        return $next($request);
    }
}
