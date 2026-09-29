<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends TenantModel
{
    protected $fillable = ['amount', 'mode', 'reference_no', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return ['paid_at' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
