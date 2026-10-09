<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Plot extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUSES = [
        'available' => 'Available',
        'booked' => 'Booked',
        'sale_init' => 'Sale Init',
        'ror' => 'ROR',
        'ror_init' => 'ROR-Init',
        'ror_completed' => 'ROR-Completed',
        'sold' => 'Sold',
        'blocked' => 'Blocked',
    ];

    /** Hex colours used on the plot map, badges and charts. */
    public const COLORS = [
        'available' => '#0F8F84',
        'booked' => '#D97706',
        'sale_init' => '#2563EB',
        'ror' => '#7C3AED',
        'ror_init' => '#9333EA',
        'ror_completed' => '#6D28D9',
        'sold' => '#475569',
        'blocked' => '#DC2626',
    ];

    public const FACINGS = ['East', 'West', 'North', 'South', 'North-East', 'North-West', 'South-East', 'South-West'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'offer_valid_till' => 'date',
            'offer_active' => 'boolean',
            'is_corner' => 'boolean',
            'map_polygon' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PlotStatusHistory::class)->latest('id');
    }

    public function actualPrice(): float
    {
        return round((float) $this->size_sqft * (float) $this->rate_per_sqft, 2);
    }

    public function offerPrice(): ?float
    {
        return $this->offer_rate_per_sqft !== null ? round((float) $this->size_sqft * (float) $this->offer_rate_per_sqft, 2) : null;
    }

    /** Offer is active while today ≤ offer_valid_till (and the nightly job has not switched it off). */
    public function offerIsActive(?CarbonInterface $on = null): bool
    {
        $on ??= today();

        return $this->offer_rate_per_sqft !== null
            && $this->offer_valid_till !== null
            && $on->startOfDay()->lte($this->offer_valid_till)
            && (float) $this->offer_rate_per_sqft < (float) $this->rate_per_sqft;
    }

    /** Price used for booking/sale on the given date: offer price if active, else actual price. */
    public function priceOn(?CarbonInterface $on = null): float
    {
        return $this->offerIsActive($on) ? $this->offerPrice() : $this->actualPrice();
    }

    public function currentPrice(): float
    {
        return $this->priceOn(today());
    }

    public function currentRate(): float
    {
        return $this->offerIsActive() ? (float) $this->offer_rate_per_sqft : (float) $this->rate_per_sqft;
    }

    public function savingAmount(): float
    {
        return $this->offerIsActive() ? round($this->actualPrice() - $this->offerPrice(), 2) : 0;
    }

    public function offerDaysLeft(): int
    {
        return $this->offerIsActive() ? (int) today()->diffInDays($this->offer_valid_till) : 0;
    }

    public function cents(): float
    {
        return round((float) $this->size_sqft / 435.6, 2);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function color(): string
    {
        return self::COLORS[$this->status] ?? '#64748B';
    }

    public function dimensions(): ?string
    {
        return $this->length_ft && $this->width_ft ? rtrim(rtrim((string) $this->length_ft, '0'), '.').' × '.rtrim(rtrim((string) $this->width_ft, '0'), '.').' ft' : null;
    }

    public function publicUrl(): string
    {
        return route('market.plot', ['project' => $this->project->slug, 'plotNo' => strtolower($this->plot_no)]);
    }

    /** Change status and record history. */
    public function moveTo(string $status, ?string $reason = null): void
    {
        $from = $this->status;
        if ($from === $status) {
            return;
        }
        $this->status = $status;
        $this->save();
        PlotStatusHistory::create([
            'tenant_id' => $this->tenant_id,
            'plot_id' => $this->id,
            'from_status' => $from,
            'to_status' => $status,
            'reason' => $reason,
            'user_id' => Auth::guard('web')->id(),
        ]);
    }

    /** Data for tooltips/cards (JSON, rendered by public/js/app.js). */
    public function tooltipData(): array
    {
        return [
            'no' => $this->plot_no,
            'patta' => $this->patta_number,
            'sqft' => (float) $this->size_sqft,
            'cents' => $this->cents(),
            'dim' => $this->dimensions(),
            'facing' => $this->facing,
            'e' => $this->east_boundary,
            'w' => $this->west_boundary,
            'n' => $this->north_boundary,
            's' => $this->south_boundary,
            'road' => $this->road_width_ft ? (float) $this->road_width_ft : null,
            'corner' => $this->is_corner,
            'status' => $this->statusLabel(),
            'statusKey' => $this->status,
            'actual' => $this->actualPrice(),
            'offer' => $this->offerIsActive() ? $this->offerPrice() : null,
            'offerText' => $this->offerIsActive() ? $this->offer_text : null,
            'offerTill' => $this->offerIsActive() ? $this->offer_valid_till->format('d-m-Y') : null,
            'rate' => $this->currentRate(),
            'url' => $this->project ? $this->publicUrl() : null,
        ];
    }
}
