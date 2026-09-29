<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StageTemplate extends TenantModel
{

    protected $fillable = ['stage_no', 'seq_no', 'name', 'description', 'cost', 'duration_days', 'stage_type', 'is_mandatory'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'cost' => 'decimal:2'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StageGroup::class, 'stage_group_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TaskTemplate::class)->orderBy('task_no');
    }

    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(StageTemplate::class, 'stage_template_links', 'stage_template_id', 'depends_on_id')
            ->withPivot('tenant_id');
    }
}
