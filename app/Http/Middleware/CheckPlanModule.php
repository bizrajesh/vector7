<?php

namespace App\Http\Middleware;

use App\Services\PlanLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** module:launch — the tenant's plan must include the module. */
class CheckPlanModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = $request->user('web')?->tenant;
        if ($tenant && ! PlanLimiter::moduleEnabled($tenant, $module)) {
            $label = config('permissions.plan_modules.'.$module, $module);
            if ($request->isMethod('GET')) {
                return response()->view('ws.upgrade', ['module' => $label], 402);
            }

            return back()->with('error', "$label is not included in your plan. Upgrade your plan to use it.")->with('upgrade', true);
        }

        return $next($request);
    }
}
