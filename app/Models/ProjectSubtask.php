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

class ProjectSubtask extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['depends_on' => 'array', 'planned_start' => 'date', 'planned_end' => 'date', 'actual_start' => 'date', 'actual_end' => 'date'];
    }

    public const STATUSES = ['not_started' => 'Not started', 'in_progress' => 'In progress', 'blocked' => 'Blocked', 'done' => 'Done'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function stage(): BelongsTo { return $this->belongsTo(ProjectStage::class, 'project_stage_id'); }
    public function documents(): HasMany { return $this->hasMany(ProjectDocument::class); }
}
