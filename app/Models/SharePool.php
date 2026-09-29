<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharePool extends TenantModel
{
    protected $fillable = ['face_value'];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class)->withTrashed();
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(ShareIssuance::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShareValueEvent::class)->orderBy('occurred_at');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(SharePayout::class);
    }

    public function growthPct(): float
    {
        $face = (float) $this->face_value;

        return $face > 0 ? round(((float) $this->current_share_value - $face) / $face * 100, 2) : 0.0;
    }
}
