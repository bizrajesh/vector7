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

class ProjectDocument extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'uploaded_at' => 'datetime'];
    }

    public function subtask(): BelongsTo { return $this->belongsTo(ProjectSubtask::class, 'project_subtask_id'); }
    public function file(): BelongsTo { return $this->belongsTo(StorageFile::class, 'storage_file_id'); }
}
