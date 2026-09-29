<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShareValueEvent extends TenantModel
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ShareAllocation::class);
    }
}
