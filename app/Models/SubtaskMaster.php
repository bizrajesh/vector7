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

class SubtaskMaster extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stage(): BelongsTo { return $this->belongsTo(StageMaster::class, 'stage_master_id'); }
    public function dependencies(): BelongsToMany { return $this->belongsToMany(SubtaskMaster::class, 'subtask_dependencies', 'subtask_master_id', 'depends_on_id'); }
    public function documents(): BelongsToMany { return $this->belongsToMany(DocumentChecklist::class, 'subtask_documents'); }
}
