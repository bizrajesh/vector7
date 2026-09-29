<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareAllocation extends TenantModel
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = [];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ShareValueEvent::class, 'share_value_event_id');
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class)->withTrashed();
    }
}
