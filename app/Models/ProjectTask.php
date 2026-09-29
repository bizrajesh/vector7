<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends TenantModel
{

    protected $fillable = ['task_no', 'name', 'description', 'effort_days', 'weight_pct', 'notes'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'done_at' => 'datetime'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'project_stage_id');
    }
}
