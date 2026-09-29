<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharePayout extends TenantModel
{
    protected $fillable = ['amount', 'paid_on', 'mode', 'reference_no'];

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class)->withTrashed();
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(SharePool::class, 'share_pool_id');
    }
}
