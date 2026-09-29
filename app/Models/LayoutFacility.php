<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayoutFacility extends TenantModel
{

    protected $fillable = ['facility_id', 'qty', 'unit_cost', 'total', 'area_sqft'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class)->withTrashed();
    }
}
