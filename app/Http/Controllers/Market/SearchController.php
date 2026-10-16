<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Listings\ListingTypes;
use App\Models\CustomerShortlist;
use App\Models\SavedSearch;
use App\Services\PropertySearch;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Marketplace search (spec 17.7 / 17.8).
 *  /search?q=…            plain-words (AI) search — DB results only, relaxed when < 5, ranked by Claude when available
 *  /search?type=…&place=… filter search (no AI)
 *  /buy, /rent, /buy/flats, /buy/flats/thanjavur  SEO category pages (same results page)
 */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $previous = $request->boolean('refine') ? PropertySearch::fromRequest($request->query()) : null;
            $res = PropertySearch::ai($q, $previous, auth('customer')->id(), $request->ip());

            return $this->render($request, [
                'mode' => 'ai', 'q' => $q, 'items' => $res['items'], 'total' => $res['items']->count(), 'filters' => $res['filters'],
                'relaxed' => $res['relaxed'], 'usedAi' => $res['used_ai'], 'title' => 'Results for “'.Str::limit($q, 60).'”',
                'seo' => ['title' => 'Search: '.Str::limit($q, 50), 'description' => 'Property search results on vector7.', 'robots' => 'noindex,follow', 'canonical' => route('market.search')],
            ]);
        }
        $filters = PropertySearch::fromRequest($request->query());

        return $this->filtered($request, $filters, $this->titleFor($filters), ['robots' => 'noindex,follow', 'canonical' => route('market.search')]);
    }

    /** /buy | /rent | /{purpose}/{type} | /{purpose}/{type}/{city} */
    public function category(Request $request, string $purpose, ?string $type = null, ?string $city = null)
    {
        $typeKey = null;
        if ($type !== null) {
            $typeKey = $type === ListingTypes::LAYOUT_SLUG ? 'layout' : (ListingTypes::fromSlug($type) ?? abort(404));
            if ($typeKey === 'layout' && $purpose === 'rent') {
                abort(404);
            }
        }
        $place = $city ? $this->placeFromSlug($city) : null;
        if ($city && ! $place) {
            abort(404);
        }
        $filters = array_replace(PropertySearch::fromRequest($request->query()), array_filter([
            'types' => $typeKey ? ($typeKey === 'plot' ? ['plot', 'layout'] : [$typeKey]) : null,
            'purpose' => $purpose === 'rent' ? 'rent' : 'sale',
            'places' => $place ? [$place] : null,
        ]));
        $filters = PropertySearch::clean($filters);
        $title = $this->titleFor($filters);
        $canonical = PropertySearch::categoryUrl($typeKey ?? 'layout', $filters['purpose'], $place);
        if (! $typeKey) {
            $canonical = route('market.category', ['purpose' => $purpose]);
        }
        // Filtered variants of a category page are not separate pages for search engines.
        $robots = count($request->query()) > 0 ? 'noindex,follow' : 'index,follow';

        return $this->filtered($request, $filters, $title, ['canonical' => $canonical, 'robots' => $robots, 'breadcrumbs' => true]);
    }

    private function filtered(Request $request, array $filters, string $title, array $seo)
    {
        $sort = array_key_exists($request->query('sort'), PropertySearch::SORTS) ? $request->query('sort') : 'relevance';
        $res = PropertySearch::search($filters, $sort, false, 600);
        $meta = SeoService::meta('page', 'search:'.md5($title), $title, $this->descriptionFor($filters, $res['items']->count()));

        return $this->render($request, [
            'mode' => 'filters', 'q' => '', 'items' => $res['items'], 'total' => $res['items']->count(), 'filters' => $filters, 'relaxed' => [], 'usedAi' => false,
            'title' => $title, 'sort' => $sort, 'seo' => $meta + $seo,
        ]);
    }

    private function render(Request $request, array $data)
    {
        $view = in_array($request->query('view'), ['grid', 'list', 'map'], true) ? $request->query('view') : 'grid';
        $items = $data['items'] instanceof Collection ? $data['items'] : collect($data['items']);
        $page = max(1, (int) $request->query('page', 1));
        $per = $view === 'map' ? 200 : 24;
        $paged = new LengthAwarePaginator($items->forPage($page, $per)->values(), $items->count(), $per, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $customer = auth('customer')->user();
        $shortlisted = $customer ? CustomerShortlist::where('customer_id', $customer->id)->get()->map(fn ($s) => $s->listing_id ? 'L:'.$s->listing_id : 'P:'.$s->project_id)->flip()->all() : [];
        $seo = $data['seo'];
        $jsonld = [];
        if (! empty($seo['breadcrumbs'])) {
            $jsonld[] = SeoService::breadcrumbs([['Home', route('home')], [$data['title'], $seo['canonical']]]);
            $jsonld[] = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $data['title'],
                'itemListElement' => $items->take(20)->values()->map(fn ($i, $n) => ['@type' => 'ListItem', 'position' => $n + 1, 'url' => $i['url'], 'name' => $i['title']])->all()];
        }
        unset($seo['breadcrumbs']);

        return view('market.search', $data + [
            'view' => $view, 'paged' => $paged, 'sort' => $data['sort'] ?? 'relevance', 'chips' => PropertySearch::chips($data['filters']),
            'query' => PropertySearch::toQuery($data['filters']), 'shortlisted' => $shortlisted, 'seo' => $seo, 'jsonld' => $jsonld,
            'places' => PropertySearch::knownPlaces()->sort()->values(),
            'pins' => $view === 'map' ? $items->filter(fn ($i) => $i['lat'] !== null && $i['lng'] !== null)->take(300)->map(fn ($i) => [
                'lat' => (float) $i['lat'], 'lng' => (float) $i['lng'], 'title' => $i['title'], 'type' => $i['type_label'],
                'price' => ($i['price_from'] ? 'From ' : '').\App\Support\Format::inr($i['price']).$i['price_suffix'], 'url' => $i['url'],
            ])->values() : collect(),
        ]);
    }

    /** Customer: save the current search with email alerts. */
    public function save(Request $request)
    {
        $customer = auth('customer')->user();
        $filters = PropertySearch::fromRequest($request->input());
        abort_if(SavedSearch::where('customer_id', $customer->id)->count() >= 20, 422, 'You can save up to 20 searches.');
        SavedSearch::create(['customer_id' => $customer->id, 'query_text' => mb_substr((string) $request->input('q'), 0, 500) ?: null, 'filters' => $filters, 'alerts' => true]);

        return back()->with('ok', 'Search saved. We will email you when a new property matches it.');
    }

    private function placeFromSlug(string $slug): ?string
    {
        return PropertySearch::knownPlaces()->first(fn ($p) => Str::slug($p) === $slug);
    }

    private function titleFor(array $f): string
    {
        $types = $f['types'];
        $what = match (true) {
            $types === ['plot', 'layout'] || $types === ['layout', 'plot'] => 'Plots',
            $types === ['layout'] => 'Approved layouts',
            count($types) === 1 => ListingTypes::get($types[0])->plural(),
            default => 'Properties',
        };
        $purpose = $f['purpose'] === 'rent' ? ' for rent' : ($f['purpose'] === 'sale' ? ' for sale' : '');
        $where = $f['places'] ? ' in '.implode(', ', $f['places']) : '';

        return $what.$purpose.$where;
    }

    private function descriptionFor(array $f, int $count): string
    {
        return $this->titleFor($f).' — '.$count.' verified '.($count === 1 ? 'property' : 'properties').' listed by promoters and owners on vector7, with photos, prices, offers and map location.';
    }
}
