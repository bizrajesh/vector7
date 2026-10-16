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

class SubscriptionInvoice extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'paid_at' => 'datetime'];
    }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function topup(): BelongsTo { return $this->belongsTo(TenantTopup::class, 'tenant_topup_id'); }

    /** What the invoice is for, e.g. "Growth plan (monthly)" or "Top-up: 5 GB extra storage × 2". */
    public function itemLabel(): string
    {
        if ($this->kind === 'topup') {
            return $this->description ?: 'Top-up';
        }

        return ($this->plan?->name ?? 'Plan').' plan'.($this->plan ? ' ('.$this->plan->billing_cycle.')' : '');
    }
}
