<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplate extends TenantModel
{

    protected $fillable = ['task_no', 'name', 'description', 'effort_days', 'weight_pct'];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(StageTemplate::class, 'stage_template_id');
    }
}
