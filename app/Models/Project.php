<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    public const SQFT_PER_ACRE = 43560;

    public const STATUSES = [
        'draft' => 'Draft',
        'init' => 'Init',
        'go_no_go' => 'Go / No-Go',
        'in_progress' => 'In Progress',
        'ready_to_launch' => 'Ready to Launch',
        'launched' => 'Launched',
        'closed' => 'Closed',
    ];

    public const APPROVAL_TYPES = ['Village', 'Town', 'City'];

    public const LAND_CLASSES = ['Dry (Punjai)', 'Wet (Nanjai)', 'Manavari', 'Natham', 'Converted', 'Other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'gallery' => 'array',
            'budget_alerts_sent' => 'array',
            'decision_on' => 'date',
            'start_date' => 'date',
            'est_end_date' => 'date',
            'actual_end_date' => 'date',
            'launched_at' => 'datetime',
            'is_featured' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $p) {
            if (! $p->slug) {
                $base = Str::slug($p->name.' '.$p->location);
                $slug = $base;
                $i = 2;
                while (static::withoutGlobalScopes()->withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $p->slug = $slug;
            }
        });
    }

    /** Direct launch (spec 17.1): approved layout published without estimate / Go-No-Go / stage tracking. */
    public function isDirect(): bool
    {
        return $this->launch_mode === 'direct';
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderByDesc('is_cover')->orderBy('sort')->orderBy('id');
    }

    public function featureRows(): HasMany
    {
        return $this->hasMany(ListingFeature::class)->with('master');
    }

    public function contact(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ListingContact::class, 'owner_id')->where('owner_type', 'project');
    }

    public function brochure(): BelongsTo
    {
        return $this->belongsTo(StorageFile::class, 'brochure_file_id');
    }

    public function featureNames(): array
    {
        $names = $this->featureRows->map(fn ($f) => $f->master?->name ?? $f->custom_text)->filter()->values()->all();

        return array_values(array_unique(array_merge($names, $this->facilities ?? [])));
    }

    public function featureChips(): array
    {
        $chips = $this->featureRows->map(fn ($f) => ['name' => $f->master?->name ?? $f->custom_text, 'icon' => $f->master?->icon ?? 'star'])->filter(fn ($c) => $c['name'])->values()->all();
        foreach ($this->facilities ?? [] as $f) {
            if (! collect($chips)->contains('name', $f)) {
                $chips[] = ['name' => $f, 'icon' => 'check'];
            }
        }

        return $chips;
    }

    /** Trust badge text, e.g. "DTCP LP No. 45/2026". */
    public function approvalBadge(): ?string
    {
        return $this->approval_number ? trim(($this->approval_authority ?: 'Approval').' '.$this->approval_number) : null;
    }

    public function scopeLaunched(Builder $q): Builder
    {
        return $q->where('status', 'launched');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }

    public function tenantRel(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function estimate(): HasOne
    {
        return $this->hasOne(ProjectEstimate::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class)->orderBy('stage_no');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(ProjectSubtask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function layoutFile(): BelongsTo
    {
        return $this->belongsTo(StorageFile::class, 'layout_file_id');
    }

    public function totalSqft(): float
    {
        return round((float) $this->size_acres * self::SQFT_PER_ACRE, 2);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isReadOnly(): bool
    {
        return $this->status === 'closed';
    }

    public function locationSlug(): string
    {
        return Str::slug($this->location) ?: 'tamil-nadu';
    }

    public function publicUrl(): string
    {
        return route('market.project', ['location' => $this->locationSlug(), 'project' => $this->slug]);
    }

    public function layoutUrl(): ?string
    {
        return $this->layout_file_id ? route('market.layout', $this->slug).'?v='.$this->layout_file_id : null;
    }

    public function minPrice(): ?float
    {
        $min = null;
        foreach ($this->plots->where('status', 'available') as $plot) {
            $p = $plot->currentPrice();
            $min = $min === null ? $p : min($min, $p);
        }

        return $min;
    }

    public function availableCount(): int
    {
        return $this->plots->where('status', 'available')->count();
    }
}
