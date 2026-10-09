<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EstimateLine extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    public function estimate(): BelongsTo { return $this->belongsTo(ProjectEstimate::class, 'project_estimate_id'); }
    public function facility(): BelongsTo { return $this->belongsTo(FacilityMaster::class, 'facility_master_id'); }
}
