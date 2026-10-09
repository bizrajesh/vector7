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

class PromoCode extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'project_ids' => 'array', 'is_active' => 'boolean'];
    }

    public function uses(): HasMany { return $this->hasMany(PromoCodeUse::class); }

    public function isUsableFor(int $projectId, ?\Carbon\CarbonInterface $on = null): bool
    {
        $on ??= today();
        return $this->is_active
            && $on->betweenIncluded($this->valid_from, $this->valid_to)
            && ($this->max_uses === 0 || $this->used_count < $this->max_uses)
            && (empty($this->project_ids) || in_array($projectId, array_map('intval', $this->project_ids), true));
    }

    public function discountOn(float $price): float
    {
        $d = $this->discount_type === 'percent' ? round($price * (float) $this->value / 100, 2) : (float) $this->value;
        return min($d, $price);
    }
}
