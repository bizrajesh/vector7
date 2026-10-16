<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Extra allowance on one plan limit for a tenant: bought (paid invoice) or granted by the App team. */
class TenantTopup extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function pack(): BelongsTo { return $this->belongsTo(TopupPack::class, 'topup_pack_id'); }
    public function tenantRel(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function invoice(): HasOne { return $this->hasOne(SubscriptionInvoice::class); }

    /** Active and in date today. */
    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('status', 'active')->where(fn ($w) => $w->whereNull('starts_on')->orWhere('starts_on', '<=', today()))
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', today()));
    }

    public function isCurrent(): bool
    {
        return $this->status === 'active' && (! $this->starts_on || $this->starts_on->lte(today())) && (! $this->ends_on || $this->ends_on->gte(today()));
    }

    public function label(): string
    {
        return '+'.self::formatUnits($this->key, $this->units).' '.strtolower(TopupPack::KEYS[$this->key] ?? $this->key);
    }

    public static function formatUnits(string $key, int $units): string
    {
        return $key === 'storage_mb' ? Format::bytes($units * 1048576) : Format::num($units);
    }
}
