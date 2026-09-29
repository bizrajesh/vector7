<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleInstalment extends TenantModel
{
    protected $fillable = ['seq', 'pct', 'amount', 'due_date'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function balance(): float
    {
        return round((float) $this->amount - (float) $this->paid_amount, 2);
    }
}
