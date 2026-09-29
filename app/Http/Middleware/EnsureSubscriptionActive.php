<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspended tenants are read-only; cancelled tenants are blocked.
 */
class EnsureSubscriptionActive
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();
        if (! $tenant) {
            abort(403);
        }

        $status = $tenant->status;

        if ($status === SubscriptionStatus::Cancelled) {
            return redirect()->route('billing.show')->with('error', 'This workspace is cancelled. Contact support to restore it.');
        }

        if ($status === SubscriptionStatus::Suspended && ! $request->isMethodSafe()) {
            return back()->with('error', 'Your subscription is suspended. The workspace is read-only until payment is received.');
        }

        return $next($request);
    }
}
