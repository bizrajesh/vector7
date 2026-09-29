<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareIssuance extends TenantModel
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['issued_on' => 'date'];
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
