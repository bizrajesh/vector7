<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\AiUsageLog;
use App\Models\Booking;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Format;

/**
 * Plan limits and usage metering: Limit / Used / Left over / % used.
 */
class PlanLimiter
{
    public const USER_ROLE_KEYS = [
        'tenant_admin' => 'users_tenant_admin',
        'tenant_manager' => 'users_tenant_manager',
        'tenant_sales' => 'users_tenant_sales',
        'tenant_account' => 'users_tenant_account',
        'support' => 'users_support',
    ];

    public const LIMIT_LABELS = [
        'storage_mb' => 'Storage',
        'projects' => 'Projects',
        'ai_credits' => 'AI credits / month',
        'users_tenant_admin' => 'Tenant Admin users',
        'users_tenant_manager' => 'Manager users',
        'users_tenant_sales' => 'Sales users',
        'users_tenant_account' => 'Account users',
        'users_support' => 'Support users',
    ];

    public static function used(Tenant $tenant, string $key): int|float
    {
        return match ($key) {
            'storage_mb' => round($tenant->storage_used_bytes / 1048576, 2),
            'projects' => Project::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->where('status', '!=', 'closed')->count(),
            'ai_credits' => (int) AiUsageLog::where('tenant_id', $tenant->id)->where('created_at', '>=', now()->startOfMonth())->sum('credits'),
            default => str_starts_with($key, 'users_')
                ? User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereHas('role', fn ($q) => $q->withoutGlobalScopes()->where('base_role', array_search($key, self::USER_ROLE_KEYS, true)))->count()
                : 0,
        };
    }

    /** @return array<string, array{label:string, limit:int, used:float|int, left:float|int|null, pct:float, unlimited:bool, display:string}> */
    public static function usage(Tenant $tenant): array
    {
        $plan = $tenant->plan();
        $out = [];
        foreach (self::LIMIT_LABELS as $key => $label) {
            $limit = $plan ? $plan->limit($key) : 0;
            $used = self::used($tenant, $key);
            $unlimited = $limit < 0;
            $left = $unlimited ? null : max(0, $limit - $used);
            $pct = $unlimited || $limit === 0 ? ($used > 0 && ! $unlimited ? 100 : 0) : min(100, round($used / $limit * 100, 1));
            $fmt = $key === 'storage_mb'
                ? fn ($v) => Format::bytes($v * 1048576)
                : fn ($v) => Format::num($v);
            $out[$key] = [
                'label' => $label,
                'limit' => $limit,
                'used' => $used,
                'left' => $left,
                'pct' => $pct,
                'unlimited' => $unlimited,
                'display' => [
                    'limit' => $unlimited ? 'Unlimited' : $fmt($limit),
                    'used' => $fmt($used),
                    'left' => $unlimited ? 'Unlimited' : $fmt($left),
                ],
            ];
        }

        return $out;
    }

    /** Info-only counters for this month. */
    public static function monthly(Tenant $tenant): array
    {
        $from = now()->startOfMonth();

        return [
            'plots_launched' => Plot::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('created_at', '>=', $from)->count(),
            'bookings' => Booking::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('created_at', '>=', $from)->count(),
            'sales' => Sale::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('created_at', '>=', $from)->count(),
        ];
    }

    public static function ensureActive(Tenant $tenant): void
    {
        $sub = $tenant->subscription;
        if (! $sub || ! $sub->isUsable()) {
            throw new PlanLimitException('Your subscription has expired. Renew or upgrade your plan to continue.');
        }
    }

    public static function ensure(Tenant $tenant, string $key, int $adding = 1): void
    {
        self::ensureActive($tenant);
        $limit = $tenant->plan()?->limit($key) ?? 0;
        if ($limit < 0) {
            return;
        }
        if (self::used($tenant, $key) + $adding > $limit) {
            $label = self::LIMIT_LABELS[$key] ?? $key;
            throw new PlanLimitException("Your plan allows $limit ".strtolower($label).'. Upgrade your plan to add more.');
        }
    }

    public static function ensureUserRole(Tenant $tenant, string $baseRole): void
    {
        if (isset(self::USER_ROLE_KEYS[$baseRole])) {
            self::ensure($tenant, self::USER_ROLE_KEYS[$baseRole]);
        }
    }

    public static function ensureStorage(Tenant $tenant, int $bytes): void
    {
        self::ensureActive($tenant);
        $limitMb = $tenant->plan()?->limit('storage_mb') ?? 0;
        if ($limitMb < 0) {
            return;
        }
        if ($tenant->storage_used_bytes + $bytes > $limitMb * 1048576) {
            throw new PlanLimitException('Storage limit reached ('.Format::bytes($limitMb * 1048576).'). Delete files or upgrade your plan.');
        }
    }

    public static function moduleEnabled(Tenant $tenant, string $module): bool
    {
        $plan = $tenant->plan();

        return $plan !== null && ($plan->modules === null || $plan->hasModule($module));
    }

    public static function ensureModule(Tenant $tenant, string $module): void
    {
        if (! self::moduleEnabled($tenant, $module)) {
            $label = config('permissions.plan_modules.'.$module, $module);
            throw new PlanLimitException("$label is not included in your plan. Upgrade your plan to use it.");
        }
    }

    /** Progress colour: teal < 80 %, amber 80–99 %, red at 100 %. */
    public static function level(float $pct): string
    {
        return $pct >= 100 ? 'red' : ($pct >= 80 ? 'amber' : 'teal');
    }
}
