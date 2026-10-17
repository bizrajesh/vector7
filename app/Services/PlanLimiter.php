<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\AiUsageLog;
use App\Models\Booking;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\TenantTopup;
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
        'plots' => 'Plots',
        'users' => 'Users (all roles)',
        'listings' => 'Active listings',
        'images_per_listing' => 'Images per listing',
        'ai_credits' => 'AI credits / month',
        'users_tenant_admin' => 'Tenant Admin users',
        'users_tenant_manager' => 'Manager users',
        'users_tenant_sales' => 'Sales users',
        'users_tenant_account' => 'Account users',
        'users_support' => 'Support users',
    ];

    /** The five headline plan items (Starter / Growth / Enterprise are designed on these). */
    public const PLAN_ITEMS = ['storage_mb', 'projects', 'plots', 'users', 'listings'];

    /** Optional per-role caps (-1 = no cap; the "Users (all roles)" limit still applies). */
    public const ROLE_CAP_KEYS = ['users_tenant_admin', 'users_tenant_manager', 'users_tenant_sales', 'users_tenant_account', 'users_support'];

    /** Free active listings every promoter keeps, even when the subscription has lapsed (App setting). */
    public static function freeListings(): int
    {
        return max(0, (int) AppSettings::get('listings.free', 5));
    }

    /** Plan limit + current top-ups. -1 = unlimited. */
    public static function limit(Tenant $tenant, string $key): int
    {
        $base = $tenant->plan()?->limit($key) ?? 0;
        if ($base < 0) {
            return -1;
        }

        $limit = $base + self::topups($tenant, $key);

        return $key === 'listings' ? max($limit, self::freeListings()) : $limit;
    }

    /** Units added by current top-ups (bought or granted). */
    public static function topups(Tenant $tenant, string $key): int
    {
        if (! isset(\App\Models\TopupPack::KEYS[$key])) {
            return 0;
        }

        return (int) TenantTopup::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('key', $key)->current()->sum('units');
    }

    public static function used(Tenant $tenant, string $key): int|float
    {
        return match ($key) {
            'storage_mb' => round($tenant->storage_used_bytes / 1048576, 2),
            'projects' => Project::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->where('status', '!=', 'closed')->count(),
            'plots' => Plot::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'users' => User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'ai_credits' => (int) AiUsageLog::where('tenant_id', $tenant->id)->where('created_at', '>=', now()->startOfMonth())->sum('credits'),
            'listings' => \App\Models\Listing::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->whereIn('status', \App\Models\Listing::LIVE)->count(),
            'images_per_listing' => (int) \App\Models\ListingImage::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('listing_id')
                ->selectRaw('count(*) as n')->groupBy('listing_id')->orderByDesc('n')->value('n'),
            default => str_starts_with($key, 'users_')
                ? User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereHas('role', fn ($q) => $q->withoutGlobalScopes()->where('base_role', array_search($key, self::USER_ROLE_KEYS, true)))->count()
                : 0,
        };
    }

    /** @return array<string, array{label:string, limit:int, used:float|int, left:float|int|null, pct:float, unlimited:bool, display:string}> */
    public static function usage(Tenant $tenant): array
    {
        $out = [];
        foreach (self::LIMIT_LABELS as $key => $label) {
            $limit = self::limit($tenant, $key);
            $topup = $limit < 0 ? 0 : self::topups($tenant, $key);
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
                'topup' => $topup,
                'optional' => in_array($key, self::ROLE_CAP_KEYS, true),
                'display' => [
                    'limit' => $unlimited ? 'Unlimited' : $fmt($limit),
                    'used' => $fmt($used),
                    'left' => $unlimited ? 'Unlimited' : $fmt($left),
                    'topup' => $topup ? '+'.$fmt($topup) : '',
                ],
            ];
        }

        return $out;
    }

    /** Limits that matter to a property lister (one user, no projects or plots). */
    public const LISTER_KEYS = ['listings', 'images_per_listing', 'storage_mb', 'ai_credits'];

    /** Usage rows worth alerting about for this tenant (listers: listing-related limits only). */
    public static function relevantUsage(Tenant $tenant): array
    {
        $usage = self::usage($tenant);

        return $tenant->isLister() ? array_intersect_key($usage, array_flip(self::LISTER_KEYS)) : $usage;
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
        $usable = $tenant->subscription?->isUsable() ?? false;
        // Every promoter keeps the free active listings, even without a running subscription.
        if ($key === 'listings' && ! $usable) {
            if (self::used($tenant, $key) + $adding > self::freeListings()) {
                throw new PlanLimitException('Your subscription has expired. You can keep '.self::freeListings().' listings live for free; renew or upgrade your plan to publish more.');
            }

            return;
        }
        self::ensureActive($tenant);
        $limit = self::limit($tenant, $key);
        if ($limit < 0) {
            return;
        }
        if (self::used($tenant, $key) + $adding > $limit) {
            $label = self::LIMIT_LABELS[$key] ?? $key;
            $topup = isset(\App\Models\TopupPack::KEYS[$key]) ? ' Upgrade your plan or buy a top-up to add more.' : ' Upgrade your plan to add more.';
            throw new PlanLimitException('Your plan allows '.Format::num($limit).' '.strtolower($label).'.'.$topup);
        }
    }

    public static function ensureUserRole(Tenant $tenant, string $baseRole): void
    {
        self::ensure($tenant, 'users');
        if (isset(self::USER_ROLE_KEYS[$baseRole])) {
            self::ensure($tenant, self::USER_ROLE_KEYS[$baseRole]);
        }
    }

    public static function ensureStorage(Tenant $tenant, int $bytes): void
    {
        self::ensureActive($tenant);
        $limitMb = self::limit($tenant, 'storage_mb');
        if ($limitMb < 0) {
            return;
        }
        if ($tenant->storage_used_bytes + $bytes > $limitMb * 1048576) {
            throw new PlanLimitException('Storage limit reached ('.Format::bytes($limitMb * 1048576).'). Delete files, buy a storage top-up or upgrade your plan.');
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
