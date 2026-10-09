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

class ProjectStage extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['planned_start' => 'date', 'planned_end' => 'date', 'actual_start' => 'date', 'actual_end' => 'date'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function subtasks(): HasMany { return $this->hasMany(ProjectSubtask::class)->orderBy('id'); }
}
