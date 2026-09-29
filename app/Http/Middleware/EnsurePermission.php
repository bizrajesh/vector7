<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('permission:plots.manage') — deny by default (OWASP A01). */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission($permission)) {
            SecurityLog::warning('permission_denied', ['user_id' => $user?->id, 'permission' => $permission, 'path' => $request->path()]);
            abort(403);
        }

        return $next($request);
    }
}
