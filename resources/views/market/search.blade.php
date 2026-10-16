<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    @if ($view === 'map')@include('partials.leaflet')@endif
    @php
        $typeOptions = ['layout' => 'Approved layouts'] + collect(\App\Listings\ListingTypes::all())->map->plural()->all();
        $f = $filters;
        $viewUrl = fn ($v) => request()->fullUrlWithQuery(['view' => $v, 'page' => null]);
    @endphp
    <section class="border-b border-navy-50 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            <form action="{{ route('market.search') }}" method="GET" role="search" aria-label="Describe the property you want" class="flex flex-col gap-2 sm:flex-row">
                <label for="s_q" class="sr-only">Describe the property you want</label>
                <input id="s_q" name="q" value="{{ $q }}" class="input flex-1 py-3 text-base" maxlength="500" placeholder="e.g. 2 BHK flat in Thanjavur under 50 lakh near a school · தஞ்சாவூரில் 1200 சதுர அடி மனை">
                @if ($mode === 'ai')
                    @foreach ($query as $k => $v)@foreach ((array) $v as $vv)<input type="hidden" name="{{ is_array($v) ? $k.'[]' : $k }}" value="{{ $vv }}" disabled data-refine-field>@endforeach @endforeach
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="refine" value="1" class="checkbox" data-refine> Refine these results</label>
                @endif
                <button class="btn-primary btn-pill px-6"><x-icon name="sparkles" class="h-4 w-4" /> Search</button>
            </form>
            <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $title }}</h1>
                    <p class="text-sm text-muted" aria-live="polite">{{ $total }} {{ $total === 1 ? 'property' : 'properties' }} @if ($mode === 'ai')· {{ $usedAi ? 'understood by AI, results only from vector7 listings' : 'keyword search' }}@endif</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @auth('customer')
                        <form method="POST" action="{{ route('account.searches.save') }}">@csrf
                            @foreach ($query as $k => $v)@foreach ((array) $v as $vv)<input type="hidden" name="{{ is_array($v) ? $k.'[]' : $k }}" value="{{ $vv }}">@endforeach @endforeach
                            <input type="hidden" name="q" value="{{ $q }}">
                            <button class="btn-light btn-sm"><x-icon name="bell" class="h-4 w-4" /> Save search & alert me</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-light btn-sm"><x-icon name="bell" class="h-4 w-4" /> Sign in to save this search</a>
                    @endauth
                    <div class="inline-flex rounded-lg bg-page p-1" role="group" aria-label="View">
                        @foreach (['grid' => 'grid', 'list' => 'menu', 'map' => 'map'] as $v => $icon)
                            <a href="{{ $viewUrl($v) }}" class="rounded-md px-2.5 py-1.5 text-sm no-underline {{ $view === $v ? 'bg-white font-semibold text-navy shadow-card' : 'text-muted' }}" @if ($view === $v) aria-current="true" @endif><x-icon :name="$icon" class="inline h-4 w-4" /> {{ ucfirst($v) }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
            @if ($chips)
                <ul class="mt-3 flex flex-wrap gap-2" aria-label="Active filters">
                    @foreach ($chips as [$label, $without])
                        <li><a href="{{ route('market.search', $without + ($view !== 'grid' ? ['view' => $view] : [])) }}" class="chip no-underline hover:bg-red-50" aria-label="Remove filter {{ $label }}">{{ $label }} <x-icon name="x" class="h-3.5 w-3.5" /></a></li>
                    @endforeach
                </ul>
            @endif
            @if ($relaxed)
                <div class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200" role="status">
                    <strong>We found only a few exact matches, so we widened the search:</strong> {{ implode('; ', $relaxed) }}.
                </div>
            @endif
        </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[17rem_1fr]">
        <aside>
            <details class="card lg:open" @if ($mode === 'filters') open @endif>
                <summary class="flex cursor-pointer items-center gap-2 p-4 font-bold"><x-icon name="filter" class="h-4 w-4" /> Filters</summary>
                <form action="{{ route('market.search') }}" method="GET" class="space-y-4 border-t border-navy-50 p-4 text-sm">
                    <input type="hidden" name="view" value="{{ $view }}">
                    <fieldset><legend class="label">Property type</legend>
                        @foreach ($typeOptions as $k => $label)<label class="flex items-center gap-2 py-0.5"><input type="checkbox" name="type[]" value="{{ $k }}" class="checkbox" @checked(in_array($k, $f['types'], true))> {{ $label }}</label>@endforeach
                    </fieldset>
                    <div><label class="label" for="f_purpose">For</label>
                        <select id="f_purpose" name="purpose" class="input"><option value="">Sale or rent</option><option value="sale" @selected($f['purpose'] === 'sale')>Sale</option><option value="rent" @selected($f['purpose'] === 'rent')>Rent</option></select></div>
                    <div><label class="label" for="f_place">Place</label>
                        <input id="f_place" name="place" class="input" list="f_places" value="{{ implode(', ', $f['places']) }}" placeholder="Town or district">
                        <datalist id="f_places">@foreach ($places as $p)<option value="{{ $p }}">@endforeach</datalist></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="label" for="f_bmin">Min ₹</label><input id="f_bmin" name="budget_min" type="number" min="0" step="10000" class="input" value="{{ $f['budget_min'] ? (int) $f['budget_min'] : '' }}"></div>
                        <div><label class="label" for="f_bmax">Max ₹</label><input id="f_bmax" name="budget_max" type="number" min="0" step="10000" class="input" value="{{ $f['budget_max'] ? (int) $f['budget_max'] : '' }}"></div>
                        <div><label class="label" for="f_amin">Min sq ft</label><input id="f_amin" name="area_min" type="number" min="0" step="50" class="input" value="{{ $f['area_min'] ? (int) $f['area_min'] : '' }}"></div>
                        <div><label class="label" for="f_amax">Max sq ft</label><input id="f_amax" name="area_max" type="number" min="0" step="50" class="input" value="{{ $f['area_max'] ? (int) $f['area_max'] : '' }}"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="label" for="f_bhk">BHK</label><select id="f_bhk" name="bhk" class="input"><option value="">Any</option>@foreach ([1, 2, 3, 4, 5] as $b)<option value="{{ $b }}" @selected($f['bhk_min'] === $b)>{{ $b }}{{ $b === 5 ? '+' : '' }}</option>@endforeach</select></div>
                        <div><label class="label" for="f_facing">Facing</label><select id="f_facing" name="facing" class="input"><option value="">Any</option>@foreach (\App\Services\PropertySearch::FACINGS as $x)<option @selected($f['facing'] === $x)>{{ $x }}</option>@endforeach</select></div>
                    </div>
                    <div><label class="label" for="f_feature">Feature</label><input id="f_feature" name="feature" class="input" value="{{ implode(', ', $f['features']) }}" placeholder="e.g. Borewell, Car parking"></div>
                    <label class="flex items-center gap-2"><input type="checkbox" name="approved" value="1" class="checkbox" @checked($f['approved'])> Approved / RERA only</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="offer" value="1" class="checkbox" @checked($f['offer'])> On offer</label>
                    <div><label class="label" for="f_sort">Sort by</label><select id="f_sort" name="sort" class="input">@foreach (\App\Services\PropertySearch::SORTS as $k => $l)<option value="{{ $k }}" @selected($sort === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="flex gap-2"><button class="btn-primary flex-1">Apply</button><a href="{{ route('market.search') }}" class="btn-ghost">Clear</a></div>
                </form>
            </details>
        </aside>

        <div class="min-w-0">
            @if ($total === 0)
                <div class="card card-pad text-center">
                    <p class="text-lg font-extrabold">No properties match yet</p>
                    <p class="mt-1 text-muted">Try removing a filter, or save this search and we will email you when something matching is listed.</p>
                </div>
            @elseif ($view === 'map')
                <div class="h-[70vh] min-h-[420px] overflow-hidden rounded-2xl ring-1 ring-navy-50" data-map-pins="search-pins" role="application" aria-label="Map of results"></div>
                <script type="application/json" id="search-pins" nonce="{{ $cspNonce ?? '' }}">{!! json_encode($pins, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
                <p class="mt-2 text-sm text-muted">{{ $pins->count() }} of {{ $total }} results have a map location. <a href="{{ $viewUrl('list') }}">See all as a list</a>.</p>
            @else
                <div class="{{ $view === 'list' ? 'space-y-4' : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3' }}">
                    @foreach ($paged as $item)
                        @include('partials.property-card', ['item' => $item, 'layout' => $view, 'src' => $mode === 'ai' ? 'ai' : null])
                    @endforeach
                </div>
                <div class="mt-6">{{ $paged->links() }}</div>
            @endif
        </div>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-30 hidden border-t border-navy-50 bg-white/95 p-3 backdrop-blur" data-compare-bar>
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">
            <p class="text-sm"><strong data-compare-count>0</strong> selected · compare up to 3</p>
            <a href="{{ route('market.compare') }}" class="btn-primary btn-sm" data-compare-go>Compare</a>
        </div>
    </div>
</x-layouts.public>
