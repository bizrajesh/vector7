<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUSES = ['active' => 'Active', 'converted' => 'Converted to sale', 'expired' => 'Expired', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'booked_on' => 'date',
            'valid_till' => 'date',
            'disclaimer_accepted_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'released_at' => 'datetime',
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

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function firstInstalmentAmount(): float
    {
        $pct = (float) (InstalmentPlan::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->orderBy('seq')->value('percent') ?? 30);

        return round((float) $this->net_price * $pct / 100, 2);
    }
}
