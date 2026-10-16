<?php

namespace App\Models;

use App\Listings\ListingType;
use App\Listings\ListingTypes;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Format;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Individual property listing (spec 17.2): plot, flat, farm land, commercial space, villa or house. */
class Listing extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'under_offer' => 'Under offer', 'sold' => 'Sold / Rented', 'unpublished' => 'Unpublished'];

    /** Statuses visible on the marketplace. */
    public const LIVE = ['published', 'under_offer'];

    public const MIN_IMAGES = 3;

    public const MAX_IMAGES = 20;

    protected $guarded = ['id'];

    /** Marketplace caches (sitemap, category counts, known places) follow listing changes straight away. */
    protected static function booted(): void
    {
        $flush = function () {
            foreach (['sitemap.xml', 'ps.counts', 'ps.places'] as $k) {
                \Illuminate\Support\Facades\Cache::forget($k);
            }
        };
        static::saved($flush);
        static::deleted($flush);
    }

    protected function casts(): array
    {
        return [
            'type_details' => 'array', 'offer_valid_till' => 'date', 'published_at' => 'datetime', 'negotiable' => 'boolean',
            'featured' => 'boolean', 'hidden_by_admin' => 'boolean', 'lat' => 'float', 'lng' => 'float',
        ];
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereIn('listings.status', self::LIVE)->where('listings.hidden_by_admin', false)
            ->whereHas('tenant', fn ($t) => $t->where('status', 'active'));
    }

    public function type(): ListingType
    {
        return ListingTypes::get($this->type);
    }

    public function typeLabel(): string
    {
        return __($this->type()->label());
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderByDesc('is_cover')->orderBy('sort')->orderBy('id');
    }

    public function cover(): HasOne
    {
        return $this->hasOne(ListingImage::class)->orderByDesc('is_cover')->orderBy('sort')->orderBy('id');
    }

    public function featureRows(): HasMany
    {
        return $this->hasMany(ListingFeature::class)->with('master');
    }

    public function contact(): HasOne
    {
        return $this->hasOne(ListingContact::class, 'owner_id')->where('owner_type', 'listing');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    public function tenantRel(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function brochure(): BelongsTo
    {
        return $this->belongsTo(StorageFile::class, 'brochure_file_id');
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE, true) && ! $this->hidden_by_admin;
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? $this->status);
    }

    public function purposeLabel(): string
    {
        return $this->purpose === 'rent' ? __('For rent') : __('For sale');
    }

    public function purposeSlug(): string
    {
        return $this->purpose === 'rent' ? 'rent' : 'buy';
    }

    public function citySlug(): string
    {
        return \Illuminate\Support\Str::slug($this->city ?: $this->district) ?: 'tamil-nadu';
    }

    public function publicUrl(): string
    {
        return route('market.listing', ['purpose' => $this->purposeSlug(), 'type' => $this->type()->slug(), 'city' => $this->citySlug(), 'listing' => $this->slug]);
    }

    public function location(): string
    {
        return collect([$this->area_name, $this->city, $this->district !== $this->city ? $this->district : null])->filter()->implode(', ');
    }

    // ---------- Price (same rules as plots: offer shown only while today ≤ offer_valid_till) ----------
    public function actualPrice(): float
    {
        return (float) $this->price;
    }

    public function offerIsActive(?CarbonInterface $on = null): bool
    {
        $on ??= today();

        return $this->offer_price !== null && $this->offer_valid_till !== null && $on->copy()->startOfDay()->lte($this->offer_valid_till)
            && (float) $this->offer_price < (float) $this->price;
    }

    public function currentPrice(): float
    {
        return $this->offerIsActive() ? (float) $this->offer_price : (float) $this->price;
    }

    public function savingAmount(): float
    {
        return $this->offerIsActive() ? round((float) $this->price - (float) $this->offer_price, 2) : 0;
    }

    public function offerDaysLeft(): int
    {
        return $this->offerIsActive() ? (int) today()->diffInDays($this->offer_valid_till) : 0;
    }

    public function pricePerSqft(): ?float
    {
        return $this->area_sqft > 0 && $this->purpose === 'sale' ? round($this->currentPrice() / (float) $this->area_sqft, 0) : null;
    }

    public function priceSuffix(): string
    {
        return $this->purpose === 'rent' ? ' '.__('/ month') : '';
    }

    public function specs(): array
    {
        return $this->type()->specs($this);
    }

    /** Feature names (ticked masters + custom highlights). */
    public function featureNames(): array
    {
        return $this->featureRows->map(fn ($f) => $f->master?->name ?? $f->custom_text)->filter()->values()->all();
    }

    public function featureChips(): array
    {
        return $this->featureRows->map(fn ($f) => ['name' => $f->master?->name ?? $f->custom_text, 'icon' => $f->master?->icon ?? 'star'])->filter(fn ($c) => $c['name'])->values()->all();
    }

    /** Publish rules: at least 3 images, price, location and one contact mode (spec 17.2). */
    public function publishProblems(): array
    {
        $p = [];
        if ($this->images()->count() < self::MIN_IMAGES) {
            $p[] = 'Add at least '.self::MIN_IMAGES.' images.';
        }
        if (! $this->price || $this->price <= 0) {
            $p[] = 'Enter the price.';
        }
        if (! $this->city || ! $this->district) {
            $p[] = 'Enter the location (city / village and district).';
        }
        $c = $this->contact;
        if (! $c || ! ($c->allow_call || $c->allow_whatsapp || $c->allow_email)) {
            $p[] = 'Choose at least one mode of contact.';
        }
        if ($this->type()->requiresPatta() && ! $this->patta_number && ! $this->survey_number) {
            $p[] = 'Enter the patta or survey number.';
        }

        return $p;
    }

    public function money(?float $v = null): string
    {
        return Format::inr($v ?? $this->currentPrice());
    }
}
