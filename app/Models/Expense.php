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

class Expense extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['spent_on' => 'date'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function stage(): BelongsTo { return $this->belongsTo(ProjectStage::class, 'project_stage_id'); }
    public function estimateLine(): BelongsTo { return $this->belongsTo(EstimateLine::class); }
    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function bill(): BelongsTo { return $this->belongsTo(StorageFile::class, 'bill_file_id'); }
}
