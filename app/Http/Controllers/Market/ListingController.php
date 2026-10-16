<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Listings\ListingTypes;
use App\Models\CustomerShortlist;
use App\Models\Listing;
use App\Models\ListingContact;
use App\Models\ListingReport;
use App\Services\PropertySearch;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public listing page (spec 17.7): /{buy|rent}/{type}/{city}/{listing-slug}.
 * Wrong purpose / type / city segments 301 to the canonical URL. Sold or rented-out listings answer 410 Gone
 * (with similar properties) so search engines drop them; drafts and hidden listings are 404.
 */
class ListingController extends Controller
{
    public function show(Request $request, string $purpose, string $type, string $city, string $listing)
    {
        $listing = Listing::with(['images', 'contact', 'featureRows.master', 'tenantRel', 'brochure'])->where('slug', Str::lower($listing))->first();
        abort_unless($listing && ! $listing->hidden_by_admin && $listing->tenantRel?->status === 'active', 404);
        if (in_array($listing->status, ['sold', 'rented'], true)) {
            $item = PropertySearch::listingItem($listing);

            return response()->view('market.listing-gone', [
                'listing' => $listing, 'similar' => PropertySearch::similar($item, 6),
                'seo' => ['title' => __(':title – no longer available', ['title' => $listing->title]), 'description' => __('This property is no longer available. See similar properties on vector7.'), 'noindex' => true],
            ], 410);
        }
        abort_unless($listing->isLive(), 404);
        if ($request->url() !== $listing->publicUrl()) {
            return redirect($listing->publicUrl().($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }
        // Count one view per visitor per listing per day (no cookies needed).
        $viewKey = 'lv:'.$listing->id.':'.sha1($request->ip().'|'.$request->userAgent()).':'.today()->toDateString();
        if (Cache::add($viewKey, 1, 86400)) {
            Listing::withoutTimestamps(fn () => $listing->increment('views'));
        }
        $item = PropertySearch::listingItem($listing);
        $d = SeoService::listingDefaults($listing);
        $customer = auth('customer')->user();
        $typeLabel = $listing->type()->plural();

        return view('market.listing', [
            'listing' => $listing, 'item' => $item, 'contact' => $listing->contact, 'tenant' => $listing->tenantRel,
            'masked' => ListingContact::mask($listing->contact?->mobile),
            'similar' => PropertySearch::similar($item, 4),
            'shortlisted' => $customer && CustomerShortlist::where('customer_id', $customer->id)->where('listing_id', $listing->id)->exists(),
            'youtubeId' => \App\Services\PropertyMedia::youtubeId($listing->youtube_url),
            'src' => $request->query('src') === 'ai' ? 'ai' : null,
            'seo' => SeoService::meta('listing', $listing->listing_code, $d['title'], $d['description']) + ['canonical' => $listing->publicUrl(), 'image' => $listing->images->first()?->url(), 'type' => 'website'],
            'jsonld' => [
                SeoService::breadcrumbs([['Home', route('home')], [$typeLabel.' '.($listing->purpose === 'rent' ? 'for rent' : 'for sale'), PropertySearch::categoryUrl($listing->type, $listing->purpose)],
                    [$typeLabel.' in '.$listing->city, PropertySearch::categoryUrl($listing->type, $listing->purpose, $listing->city)], [$listing->title, $listing->publicUrl()]]),
                self::jsonLd($listing),
            ],
        ]);
    }

    /** schema.org RealEstateListing with Offer, Place and GeoCoordinates. */
    public static function jsonLd(Listing $l): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $l->title,
            'url' => $l->publicUrl(),
            'description' => Str::limit((string) $l->description, 300),
            'datePosted' => $l->published_at?->toDateString(),
            'image' => $l->images->take(5)->map(fn ($i) => $i->url())->values()->all() ?: null,
            'about' => array_filter(['@type' => 'Place', 'name' => $l->title,
                'address' => array_filter(['@type' => 'PostalAddress', 'streetAddress' => $l->address_line1, 'addressLocality' => $l->city, 'addressRegion' => $l->state, 'postalCode' => $l->pin, 'addressCountry' => 'IN']),
                'geo' => $l->lat !== null && $l->lng !== null ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $l->lat, 'longitude' => (float) $l->lng] : null,
                'floorSize' => $l->area_sqft ? ['@type' => 'QuantitativeValue', 'value' => (float) $l->area_sqft, 'unitCode' => 'FTK'] : null,
            ]),
            'offers' => array_filter(['@type' => 'Offer', 'price' => round($l->currentPrice()), 'priceCurrency' => 'INR',
                'availability' => $l->status === 'under_offer' ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/InStock',
                'priceValidUntil' => $l->offerIsActive() ? $l->offer_valid_till->toDateString() : null,
                'businessFunction' => $l->purpose === 'rent' ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell']),
        ]);
    }

    /** Buyers report a wrong / fake / already-sold listing (rate-limited; reviewed by the App team). */
    public function report(Request $request, Listing $listing)
    {
        abort_unless($listing->isLive(), 404);
        $data = $request->validate(['reason' => 'required|in:'.implode(',', array_keys(ListingReport::REASONS)), 'details' => 'nullable|string|max:1000', 'website' => 'prohibited']);
        $ipHash = hash('sha256', $request->ip().config('app.key'));
        if (! ListingReport::withoutGlobalScopes()->where('listing_id', $listing->id)->where('ip_hash', $ipHash)->where('created_at', '>', now()->subDay())->exists()) {
            app(\App\Support\Tenancy::class)->withoutScope(fn () => ListingReport::create(['tenant_id' => $listing->tenant_id, 'listing_id' => $listing->id,
                'customer_id' => auth('customer')->id(), 'reason' => $data['reason'], 'details' => $data['details'] ?? null, 'ip_hash' => $ipHash]));
        }

        return back()->with('ok', __('Thank you — the vector7 team will review this listing.'));
    }

    public static function typeSlug(string $type): string
    {
        return $type === 'layout' ? ListingTypes::LAYOUT_SLUG : ListingTypes::get($type)->slug();
    }
}
