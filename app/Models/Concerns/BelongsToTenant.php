<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Row-level tenant isolation (OWASP A01).
 * - Reads are always filtered by the current tenant; with no tenant in
 *   context the query returns nothing (deny by default).
 * - Creates are stamped with the current tenant; a mismatched tenant_id is refused.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = app(TenantContext::class)->id();
            $column = $builder->getModel()->qualifyColumn('tenant_id');

            $tenantId === null
                ? $builder->whereRaw('1 = 0')
                : $builder->where($column, $tenantId);
        });

        static::creating(function ($model) {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new LogicException('Cannot create '.static::class.' without a tenant context.');
            }

            if ($model->tenant_id !== null && (int) $model->tenant_id !== $tenantId) {
                throw new LogicException('Cross-tenant write refused.');
            }

            $model->tenant_id = $tenantId;
        });

        static::updating(function ($model) {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException('tenant_id is immutable.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Explicit, greppable escape hatch for platform (Super Admin) aggregates.
     */
    public static function acrossTenants(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }
}
