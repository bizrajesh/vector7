<?php

namespace App\Models;

use App\Enums\LayoutStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Layout extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'location', 'village', 'taluk', 'district', 'latitude', 'longitude',
        'total_sqft', 'sellable_pct', 'std_plot_sqft', 'default_rate_sqft', 'land_cost',
        'contingency_pct', 'planned_launch_date', 'is_public', 'public_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => LayoutStatus::class,
            'is_public' => 'boolean',
            'planned_launch_date' => 'date',
            'submitted_at' => 'datetime',
            'launched_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function stageGroup(): BelongsTo
    {
        return $this->belongsTo(StageGroup::class)->withTrashed();
    }

    public function owners(): HasMany
    {
        return $this->hasMany(LayoutOwner::class);
    }

    public function surveyNumbers(): HasMany
    {
        return $this->hasMany(LayoutSurveyNumber::class);
    }

    /** Alias used by scoped route bindings (layouts/{layout}/surveys/{survey}). */
    public function surveys(): HasMany
    {
        return $this->surveyNumbers();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LayoutDocument::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class)->orderBy('seq_no');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(LayoutFacility::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(LayoutEstimate::class)->orderByDesc('version');
    }

    public function latestEstimate(): HasOne
    {
        return $this->hasOne(LayoutEstimate::class)->latestOfMany('version');
    }

    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }

    public function sharePool(): HasOne
    {
        return $this->hasOne(SharePool::class);
    }

    public function notificationGroups(): BelongsToMany
    {
        return $this->belongsToMany(NotificationGroup::class, 'layout_notification_groups')->withPivot('tenant_id');
    }

    public function sellableSqft(): float
    {
        $deducted = (float) $this->facilities()->sum('area_sqft');

        return max(0, (float) $this->total_sqft * (float) $this->sellable_pct / 100 - $deducted);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [LayoutStatus::Draft], true);
    }
}
