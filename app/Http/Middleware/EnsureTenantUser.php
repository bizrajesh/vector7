<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Tenant workspace: sets the current tenant so the TenantScope filters every query. */
class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        if (! $user || $user->isAppUser()) {
            return redirect()->route('app.dashboard');
        }
        $tenant = $user->tenant;
        app(Tenancy::class)->set($tenant);
        View::share('currentTenant', $tenant);

        return $next($request);
    }
}
