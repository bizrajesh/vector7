<?php

namespace App\Models;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Features / amenities per listing type. App-level rows (tenant_id NULL) are shared; tenants add their own. */
class FeatureMaster extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public const GROUPS = ['Basic', 'Security', 'Lifestyle', 'Connectivity', 'Water & Power', 'Legal'];

    /** App features + the current tenant's own features for a type. */
    public static function forType(string $type, ?int $tenantId = null): Builder
    {
        $tenantId ??= app(Tenancy::class)->id();

        return self::query()->where('is_active', true)->whereIn('listing_type', [$type, 'all'])
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($w) => $w->orWhere('tenant_id', $tenantId)))
            ->orderBy('group')->orderBy('sort')->orderBy('name');
    }
}
