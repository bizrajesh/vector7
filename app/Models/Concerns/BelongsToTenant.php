<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $tenancy = app(Tenancy::class);
            if (empty($model->tenant_id) && $tenancy->id() !== null) {
                $model->tenant_id = $tenancy->id();
            }
            // A tenant user can never write into another tenant.
            if ($tenancy->id() !== null && (int) $model->tenant_id !== $tenancy->id()) {
                abort(403, 'Cross-tenant write blocked.');
            }
        });

        static::updating(function ($model) {
            $tenancy = app(Tenancy::class);
            if ($tenancy->id() !== null && (int) $model->getOriginal('tenant_id') !== $tenancy->id()) {
                abort(403, 'Cross-tenant write blocked.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
