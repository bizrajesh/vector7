<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUSES = [
        'sale_init' => 'Sale Init',
        'ror' => 'ROR (fully paid)',
        'refund_pending' => 'Refund pending',
        'refunded' => 'Refunded',
        'completed' => 'Completed',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'window_end' => 'date',
            'disclaimer_accepted_at' => 'datetime',
        ];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Broker::class);
    }

    public function instalments(): HasMany
    {
        return $this->hasMany(Instalment::class)->orderBy('seq');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class)->latestOfMany();
    }

    public function registration(): HasOne
    {
        return $this->hasOne(Registration::class);
    }

    public function nextDue(): ?Instalment
    {
        return $this->instalments->first(fn ($i) => $i->status !== 'paid');
    }

    public function isWindowMissed(): bool
    {
        return (float) $this->due_amount > 0 && today()->gt($this->window_end);
    }
}
