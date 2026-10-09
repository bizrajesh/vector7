<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** perm:module.action  (several keys with "|" = any of them) */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $keys): Response
    {
        $user = $request->user('web');
        foreach (explode('|', $keys) as $key) {
            if ($user && $user->hasPerm($key)) {
                return $next($request);
            }
        }
        abort(403, 'You do not have permission for this action.');
    }
}
