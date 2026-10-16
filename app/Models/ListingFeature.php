<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingFeature extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    public function master(): BelongsTo { return $this->belongsTo(FeatureMaster::class, 'feature_master_id'); }
}
