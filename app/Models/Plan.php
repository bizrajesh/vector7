<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'description', 'badge', 'price_monthly', 'price_yearly', 'trial_days',
        'max_users', 'max_layouts', 'max_plots', 'max_storage_mb', 'features', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->orderBy('sort_order');
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    public function price(string $cycle): string
    {
        return $cycle === 'yearly' ? $this->price_yearly : $this->price_monthly;
    }
}
