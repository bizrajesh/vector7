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

class Plan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['modules' => 'array', 'price' => 'decimal:2', 'is_active' => 'boolean', 'is_trial_default' => 'boolean'];
    }

    public function limits(): HasMany { return $this->hasMany(PlanLimit::class); }

    public function limit(string $key): int
    {
        $row = $this->relationLoaded('limits') ? $this->limits->firstWhere('key', $key) : $this->limits()->where('key', $key)->first();
        return $row ? (int) $row->value : 0;
    }

    public function hasModule(string $module): bool { return in_array($module, $this->modules ?? [], true); }
}
