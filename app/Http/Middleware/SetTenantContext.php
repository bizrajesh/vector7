<?php

namespace App\Http\Middleware;

use App\Models\ImpersonationSession;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the authenticated user (never from request input)
 * and ends impersonation sessions once they expire.
 */
class SetTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $request->session()->has('impersonation_id')) {
            $session = ImpersonationSession::find($request->session()->get('impersonation_id'));
            if (! $session || $session->ended_at || $session->expires_at->isPast()) {
                $session?->update(['ended_at' => now()]);
                $adminId = $request->session()->pull('impersonator_id');
                $request->session()->forget('impersonation_id');
                Auth::loginUsingId($adminId);
                $request->session()->regenerate();

                return redirect()->route('platform.dashboard')->with('status', 'Impersonation session ended.');
            }
        }

        if ($user && $user->tenant_id) {
            $tenant = $user->tenant()->with('plan')->first();
            $this->context->set($tenant);
            View::share('currentTenant', $tenant);
        }

        View::share('impersonating', $request->hasSession() && $request->session()->has('impersonation_id'));

        return $next($request);
    }
}
