<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStage extends TenantModel
{
    protected $fillable = [
        'stage_no', 'seq_no', 'name', 'description', 'stage_type', 'is_mandatory',
        'budget_cost', 'duration_days', 'owner_id', 'planned_start', 'planned_end',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'planned_start' => 'date',
            'planned_end' => 'date',
            'actual_start' => 'date',
            'actual_end' => 'date',
        ];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('task_no');
    }

    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(ProjectStage::class, 'project_stage_links', 'project_stage_id', 'depends_on_id')
            ->withPivot('tenant_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->where('direction', 'out');
    }

    public function actualCost(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'completed' && $this->status !== 'skipped'
            && $this->planned_end !== null && $this->planned_end->isPast();
    }
}
