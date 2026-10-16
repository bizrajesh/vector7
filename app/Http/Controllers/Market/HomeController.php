<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Listings\ListingTypes;
use App\Models\Listing;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Service;
use App\Models\Testimonial;
use App\Services\PropertySearch;
use App\Services\SeoService;

/** Marketplace home (spec 17.7): plain-words search, categories with counts, featured listings and layouts, districts, testimonials. */
class HomeController extends Controller
{
    public function home()
    {
        $featured = Project::launched()->with(['plots', 'layoutFile', 'tenantRel', 'images'])
            ->orderByDesc('is_featured')->orderByDesc('launched_at')->take(6)->get()
            ->filter(fn ($p) => $p->plots->where('status', 'available')->isNotEmpty())->values();
        $listings = Listing::live()->with(['cover', 'contact', 'featureRows.master', 'tenantRel:id,name'])->withCount('images')
            ->orderByDesc('featured')->latest('published_at')->take(8)->get()->map(fn ($l) => PropertySearch::listingItem($l));
        $counts = PropertySearch::categoryCounts();
        $categories = collect(['layout' => ['Approved layouts', 'layers']])
            ->merge(collect(ListingTypes::all())->map(fn ($t) => [$t->plural(), $t->icon()]))
            ->map(fn ($v, $k) => ['label' => $v[0], 'icon' => $v[1], 'count' => (int) ($counts[$k] ?? 0), 'url' => PropertySearch::categoryUrl($k)]);
        $stats = [
            'projects' => Project::launched()->count(),
            'available' => Plot::where('status', 'available')->whereHas('project', fn ($q) => $q->launched())->count(),
            'listings' => Listing::live()->count(),
        ];
        $seo = SeoService::meta('page', 'home', 'Plots, flats, villas & houses in Tamil Nadu', (string) \App\Services\AppSettings::get('seo.default_description'));

        return view('market.home', [
            'featured' => $featured,
            'listings' => $listings,
            'categories' => $categories,
            'districts' => PropertySearch::districtCounts(12),
            'stats' => $stats,
            'testimonials' => Testimonial::where('status', 'approved')->latest('id')->take(6)->get(),
            'services' => Service::where('is_active', true)->whereIn('available_to', ['customer', 'both'])->orderBy('sort')->take(4)->get(),
            'seo' => $seo + ['canonical' => route('home')],
            'jsonld' => [SeoService::organization(), SeoService::website()],
        ]);
    }
}
