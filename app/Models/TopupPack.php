<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A top-up the App team sells: N units of one plan limit for a monthly price (excl. GST). */
class TopupPack extends Model
{
    /** Plan limits that can be topped up. */
    public const KEYS = ['users' => 'Users', 'projects' => 'Projects', 'plots' => 'Plots', 'storage_mb' => 'Storage', 'listings' => 'Active listings'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function unitsLabel(?int $units = null): string
    {
        return TenantTopup::formatUnits($this->key, $units ?? $this->units);
    }
}
