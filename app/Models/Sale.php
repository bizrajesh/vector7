<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends TenantModel
{
    protected $fillable = ['sale_value', 'broker_id', 'commission_pct'];

    protected function casts(): array
    {
        return ['sale_date' => 'date', 'due_by' => 'date'];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Broker::class)->withTrashed();
    }

    public function instalments(): HasMany
    {
        return $this->hasMany(SaleInstalment::class)->orderBy('seq');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    public function registration(): HasOne
    {
        return $this->hasOne(Registration::class);
    }

    public function balance(): float
    {
        return round((float) $this->sale_value - (float) $this->paid_amount, 2);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'ongoing' && $this->due_by->isPast() && $this->balance() > 0;
    }
}
