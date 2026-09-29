<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends TenantModel
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class)->withTrashed();
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'project_stage_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LedgerCategory::class, 'ledger_category_id');
    }

    public function signedAmount(): float
    {
        return $this->direction === 'in' ? (float) $this->amount : -(float) $this->amount;
    }
}
