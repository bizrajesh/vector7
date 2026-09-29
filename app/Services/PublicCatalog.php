<?php

namespace App\Services;

use App\Enums\LayoutStatus;
use App\Models\Layout;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Read-only public view of launched layouts that their business chose to publish.
 * Only non-personal fields are exposed (no customer, owner or financial data).
 */
class PublicCatalog
{
    /** @return Collection<int, Layout> */
    public function layouts(int $limit = 60): Collection
    {
        $tenantIds = $this->publishingTenants()->pluck('id');

        return Layout::acrossTenants()
            ->whereIn('tenant_id', $tenantIds)
            ->where('is_public', true)
            ->where('status', LayoutStatus::Launched)
            ->with('tenant:id,name,slug,phone,email,city')
            ->withCount([
                'plots as plots_total' => fn ($q) => $q->withoutGlobalScope('tenant'),
                'plots as plots_available' => fn ($q) => $q->withoutGlobalScope('tenant')->where('status', 'available'),
            ])
            ->withMin(['plots as price_from' => fn ($q) => $q->withoutGlobalScope('tenant')->where('status', 'available')], 'cost')
            ->latest('launched_at')
            ->limit($limit)
            ->get();
    }

    /** Resolve a public layout by tenant slug + layout code, or 404. */
    public function find(string $tenantSlug, string $code): array
    {
        $tenant = $this->publishingTenants()->firstWhere('slug', strtolower($tenantSlug));
        abort_unless($tenant, 404);

        $layout = Layout::acrossTenants()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->where('is_public', true)
            ->where('status', LayoutStatus::Launched)
            ->first();
        abort_unless($layout, 404);

        return [$tenant, $layout];
    }

    /** Tenants in good standing whose plan includes public listings. */
    private function publishingTenants(): Collection
    {
        return Tenant::query()->with('plan')->whereIn('status', ['trial', 'active', 'past_due'])->get(['id', 'name', 'slug', 'phone', 'email', 'city', 'plan_id', 'status'])
            ->filter(fn (Tenant $t) => $t->plan?->hasFeature('public_listings'))
            ->values();
    }
}
