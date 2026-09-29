<?php

namespace App\Services;

use App\Models\Layout;
use App\Models\Plot;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Enforces subscription plan limits and feature flags server-side.
 */
class PlanLimits
{
    public function __construct(private readonly TenantContext $context) {}

    public function ensureCanAddUser(): void
    {
        $plan = $this->plan();
        $count = User::query()->inCurrentTenant()->where('status', '!=', 'disabled')->count();
        $this->guard($count < $plan->max_users, "Your {$plan->name} plan allows {$plan->max_users} users. Upgrade to add more.");
    }

    public function ensureCanAddLayout(): void
    {
        $plan = $this->plan();
        $this->guard(Layout::query()->count() < $plan->max_layouts, "Your {$plan->name} plan allows {$plan->max_layouts} layout projects.");
    }

    public function ensureCanAddPlots(int $adding): void
    {
        $plan = $this->plan();
        $this->guard(Plot::query()->count() + $adding <= $plan->max_plots, "Your {$plan->name} plan allows {$plan->max_plots} plots in total.");
    }

    public function feature(string $feature): bool
    {
        return (bool) $this->context->tenant()?->plan?->hasFeature($feature);
    }

    public function usage(): array
    {
        $plan = $this->plan();

        return [
            'users' => [User::query()->inCurrentTenant()->where('status', '!=', 'disabled')->count(), $plan->max_users],
            'layouts' => [Layout::query()->count(), $plan->max_layouts],
            'plots' => [Plot::query()->count(), $plan->max_plots],
        ];
    }

    private function plan()
    {
        $plan = $this->context->tenant()?->plan;
        abort_unless($plan, 403, 'No active plan.');

        return $plan;
    }

    private function guard(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['plan' => $message]);
        }
    }
}
