<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends TenantModel
{
    protected $fillable = ['amount'];

    protected function casts(): array
    {
        return ['booked_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hoursLeft(): int
    {
        return max(0, (int) now()->diffInHours($this->expires_at, false));
    }

    public function daysLeft(): int
    {
        return max(0, (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false));
    }
}
