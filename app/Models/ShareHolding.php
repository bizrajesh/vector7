<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareHolding extends TenantModel
{
    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['as_of' => 'datetime'];
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class)->withTrashed();
    }
}
