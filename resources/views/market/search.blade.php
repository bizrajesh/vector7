<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    @if ($view === 'map')@include('partials.leaflet')@endif
    @php
        $PS = \App\Services\PropertySearch::class;
        $f = $filters;
        $viewUrl = fn ($v) => request()->fullUrlWithQuery(['view' => $v, 'page' => null]);
        $chipUrl = fn (array $q) => $formAction.($q || $view !== 'grid' ? '?'.http_build_query($q + ($view !== 'grid' ? ['view' => $view] : [])) : '');
        // Which type the visitor is looking at decides the type-specific filters.
        $activeType = $pathType ?? (count($f['types']) === 1 ? $f['types'][0] : null);
        $isRent = $context === 'rent' || $f['purpose'] === 'rent';
        $homeTypes = in_array($activeType, ['flat', 'villa', 'house'], true) || ($context === 'rent' && ! $activeType);
        $budgets = $isRent
            ? [5000 => '₹5,000', 10000 => '₹10,000', 15000 => '₹15,000', 25000 => '₹25,000', 50000 => '₹50,000', 100000 => '₹1 '.__('lakh')]
            : [1000000 => '₹10 '.__('lakh'), 2500000 => '₹25 '.__('lakh'), 5000000 => '₹50 '.__('lakh'), 7500000 => '₹75 '.__('lakh'), 10000000 => '₹1 '.__('crore'), 20000000 => '₹2 '.__('crore'), 50000000 => '₹5 '.__('crore')];
        // Type pills per menu: Buy = every type; Rent = rentable types; Layouts = none (layouts only).
        $pills = match ($context) {
            'buy' => ['plot' => __('Plots & layouts'), 'flat' => __('Flats'), 'villa' => __('Villas'), 'house' => __('Independent houses'), 'farm_land' => __('Farm land'), 'commercial' => __('Commercial')],
            'rent' => ['flat' => __('Flats'), 'house' => __('Independent houses'), 'villa' => __('Villas'), 'commercial' => __('Shops & offices')],
            default => [],
        };
        $pillUrl = fn (?string $t) => $t ? $PS::categoryUrl($t, $isRent ? 'rent' : 'sale') : route('market.category', ['purpose' => $isRent ? 'rent' : 'buy']);
        $heading = ['buy' => __('Buy a property'), 'rent' => __('Rent a property'), 'layouts' => __('New approved layouts'), 'search' => null][$context];
    @endphp
    <section class="border-b border-navy-50 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            @if ($heading)<p class="text-sm font-bold uppercase tracking-wide text-teal-700">{{ $heading }}</p>@endif
            <form action="{{ route('market.search') }}" method="GET" role="search" aria-label="{{ __('Describe the property you want') }}" class="mt-1 flex flex-col gap-2 sm:flex-row">
                <label for="s_q" class="sr-only">{{ __('Describe the property you want') }}</label>
                <input id="s_q" name="q" value="{{ $q }}" class="input flex-1 py-3 text-base" maxlength="500" placeholder="{{ $isRent ? __('e.g. 2 BHK flat for rent in Trichy under 15,000 a month') : ($context === 'layouts' ? __('e.g. DTCP plot near Vallam under 15 lakh, east facing') : __('e.g. 2 BHK flat in Thanjavur under 50 lakh near a school')) }}">
                @if ($mode === 'ai')
                    @foreach ($query as $k => $v)@foreach ((array) $v as $vv)<input type="hidden" name="{{ is_array($v) ? $k.'[]' : $k }}" value="{{ $vv }}" disabled data-refine-field>@endforeach @endforeach
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="refine" value="1" class="checkbox" data-refine> {{ __('Refine these results') }}</label>
                @endif
                <button class="btn-primary btn-pill px-6"><x-icon name="sparkles" class="h-4 w-4" /> {{ __('Search') }}</button>
            </form>

            @if ($pills)
                <nav class="mt-4 flex gap-2 overflow-x-auto pb-1" aria-label="{{ __('Property type') }}">
                    <a href="{{ $pillUrl(null) }}" class="chip shrink-0 no-underline {{ ! $pathType ? 'bg-navy text-white' : '' }}" @if (! $pathType) aria-current="page" @endif>{{ __('All') }}</a>
                    @foreach ($pills as $t => $label)
                        @php($on = $pathType === $t)
                        <a href="{{ $pillUrl($t) }}" class="chip shrink-0 no-underline {{ $on ? 'bg-navy text-white' : '' }}" @if ($on) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            @endif

            <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $title }}</h1>
                    <p class="text-sm text-muted" aria-live="polite">{{ trans_choice(':count property|:count properties', $total) }} @if ($mode === 'ai')· {{ $usedAi ? __('understood by AI, results only from vector7 listings') : __('keyword search') }}@endif</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @auth('customer')
                        <form method="POST" action="{{ route('account.searches.save') }}">@csrf
                            @foreach ($query as $k => $v)@foreach ((array) $v as $vv)<input type="hidden" name="{{ is_array($v) ? $k.'[]' : $k }}" value="{{ $vv }}">@endforeach @endforeach
                            <input type="hidden" name="q" value="{{ $q }}">
                            <button class="btn-light btn-sm"><x-icon name="bell" class="h-4 w-4" /> {{ __('Save search & alert me') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-light btn-sm"><x-icon name="bell" class="h-4 w-4" /> {{ __('Sign in to save this search') }}</a>
                    @endauth
                    <div class="inline-flex rounded-lg bg-page p-1" role="group" aria-label="{{ __('View') }}">
                        @foreach (['grid' => ['grid', __('Grid')], 'list' => ['menu', __('List')], 'map' => ['map', __('Map')]] as $v => [$icon, $vl])
                            <a href="{{ $viewUrl($v) }}" class="rounded-md px-2.5 py-1.5 text-sm no-underline {{ $view === $v ? 'bg-white font-semibold text-navy shadow-card' : 'text-muted' }}" @if ($view === $v) aria-current="true" @endif><x-icon :name="$icon" class="inline h-4 w-4" /> {{ $vl }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
            @if ($chips)
                <ul class="mt-3 flex flex-wrap gap-2" aria-label="{{ __('Active filters') }}">
                    @foreach ($chips as [$label, $without])
                        <li><a href="{{ $chipUrl($without) }}" class="chip no-underline hover:bg-red-50" aria-label="{{ __('Remove filter :name', ['name' => $label]) }}">{{ $label }} <x-icon name="x" class="h-3.5 w-3.5" /></a></li>
                    @endforeach
                </ul>
            @endif
            @if ($relaxed)
                <div class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200" role="status">
                    <strong>{{ __('We found only a few exact matches, so we widened the search:') }}</strong> {{ implode('; ', $relaxed) }}.
                </div>
            @endif
        </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[17rem_1fr]">
        <aside>
            <details class="card" @if ($mode === 'filters') open @endif>
                <summary class="flex cursor-pointer items-center gap-2 p-4 font-bold"><x-icon name="filter" class="h-4 w-4" /> {{ __('Filters') }}</summary>
                <form action="{{ $formAction }}" method="GET" class="space-y-4 border-t border-navy-50 p-4 text-sm">
                    <input type="hidden" name="view" value="{{ $view }}">

                    @if ($context === 'search')
                        <fieldset><legend class="label">{{ __('Property type') }}</legend>
                            @foreach (['layout' => __('Approved layouts')] + collect(\App\Listings\ListingTypes::all())->map(fn ($t) => __($t->plural()))->all() as $k => $label)
                                <label class="flex items-center gap-2 py-0.5"><input type="checkbox" name="type[]" value="{{ $k }}" class="checkbox" @checked(in_array($k, $f['types'], true))> {{ $label }}</label>
                            @endforeach
                        </fieldset>
                        <div><label class="label" for="f_purpose">{{ __('For') }}</label>
                            <select id="f_purpose" name="purpose" class="input"><option value="">{{ __('Sale or rent') }}</option><option value="sale" @selected($f['purpose'] === 'sale')>{{ __('Sale') }}</option><option value="rent" @selected($f['purpose'] === 'rent')>{{ __('Rent') }}</option></select></div>
                    @endif

                    <div><label class="label" for="f_place">{{ __('City / district') }}</label>
                        <input id="f_place" name="place" class="input" list="f_places" value="{{ implode(', ', request()->query('place') ? (array) request()->query('place') : ($context === 'search' ? $f['places'] : [])) }}" placeholder="{{ __('Town or district') }}">
                        <datalist id="f_places">@foreach ($places as $p)<option value="{{ $p }}">@endforeach</datalist></div>

                    <div>
                        <p class="label">{{ $isRent ? __('Monthly rent') : ($context === 'layouts' || $activeType === 'plot' ? __('Plot price') : __('Budget')) }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="sr-only" for="f_bmin">{{ __('Minimum') }}</label>
                            <select id="f_bmin" name="budget_min" class="input"><option value="">{{ __('Min') }}</option>@foreach ($budgets as $v => $l)<option value="{{ $v }}" @selected((int) $f['budget_min'] === $v)>{{ $l }}</option>@endforeach</select>
                            <label class="sr-only" for="f_bmax">{{ __('Maximum') }}</label>
                            <select id="f_bmax" name="budget_max" class="input"><option value="">{{ __('Max') }}</option>@foreach ($budgets as $v => $l)<option value="{{ $v }}" @selected((int) $f['budget_max'] === $v)>{{ $l }}</option>@endforeach</select>
                        </div>
                    </div>

                    @if ($activeType === 'farm_land')
                        <div><p class="label">{{ __('Extent') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="area_min" class="input" aria-label="{{ __('Minimum extent') }}"><option value="">{{ __('Min') }}</option>@foreach ([0.5, 1, 2, 5, 10] as $a)<option value="{{ round($a * 43560) }}" @selected((int) $f['area_min'] === (int) round($a * 43560))>{{ $a }} {{ __('acres') }}</option>@endforeach</select>
                                <select name="area_max" class="input" aria-label="{{ __('Maximum extent') }}"><option value="">{{ __('Max') }}</option>@foreach ([1, 2, 5, 10, 25] as $a)<option value="{{ round($a * 43560) }}" @selected((int) $f['area_max'] === (int) round($a * 43560))>{{ $a }} {{ __('acres') }}</option>@endforeach</select>
                            </div></div>
                        <div><label class="label" for="f_water">{{ __('Water source') }}</label>
                            <select id="f_water" name="water" class="input"><option value="">{{ __('Any') }}</option>@foreach ($PS::WATER as $k => $l)<option value="{{ $k }}" @selected($f['water'] === $k)>{{ __($l) }}</option>@endforeach</select></div>
                    @elseif ($context === 'layouts' || $activeType === 'plot')
                        <div><p class="label">{{ __('Plot size') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="area_min" class="input" aria-label="{{ __('Minimum plot size') }}"><option value="">{{ __('Min') }}</option>@foreach ([600, 900, 1200, 1500, 2400] as $a)<option value="{{ $a }}" @selected((int) $f['area_min'] === $a)>{{ number_format($a) }} {{ __('sq ft') }}</option>@endforeach</select>
                                <select name="area_max" class="input" aria-label="{{ __('Maximum plot size') }}"><option value="">{{ __('Max') }}</option>@foreach ([1200, 1500, 2400, 3600, 5000] as $a)<option value="{{ $a }}" @selected((int) $f['area_max'] === $a)>{{ number_format($a) }} {{ __('sq ft') }}</option>@endforeach</select>
                            </div>
                            <p class="hint">{{ __('1 cent = 435.6 sq ft · 1 ground = 2,400 sq ft') }}</p></div>
                        <div><label class="label" for="f_facing">{{ __('Facing') }}</label>
                            <select id="f_facing" name="facing" class="input"><option value="">{{ __('Any') }}</option>@foreach ($PS::FACINGS as $x)<option value="{{ $x }}" @selected($f['facing'] === $x)>{{ __($x) }}</option>@endforeach</select></div>
                        @if ($context === 'layouts')
                            <div><label class="label" for="f_auth">{{ __('Approved by') }}</label>
                                <select id="f_auth" name="authority" class="input"><option value="">{{ __('Any authority') }}</option>@foreach ($PS::AUTHORITIES as $k => $l)<option value="{{ $k }}" @selected($f['authority'] === $k)>{{ __($l) }}</option>@endforeach</select></div>
                        @endif
                        <label class="flex items-center gap-2"><input type="checkbox" name="corner" value="1" class="checkbox" @checked($f['corner'])> {{ __('Corner plots') }}</label>
                    @elseif ($activeType === 'commercial')
                        <div><label class="label" for="f_sub">{{ __('Space type') }}</label>
                            <select id="f_sub" name="sub_type" class="input"><option value="">{{ __('Any') }}</option>@foreach ($PS::SUB_TYPES as $k => $l)<option value="{{ $k }}" @selected($f['sub_type'] === $k)>{{ __($l) }}</option>@endforeach</select></div>
                        <div><p class="label">{{ __('Area (sq ft)') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <input name="area_min" type="number" min="0" step="50" class="input" placeholder="{{ __('Min') }}" aria-label="{{ __('Minimum area') }}" value="{{ $f['area_min'] ? (int) $f['area_min'] : '' }}">
                                <input name="area_max" type="number" min="0" step="50" class="input" placeholder="{{ __('Max') }}" aria-label="{{ __('Maximum area') }}" value="{{ $f['area_max'] ? (int) $f['area_max'] : '' }}">
                            </div></div>
                    @elseif ($homeTypes || $context === 'search')
                        <div><label class="label" for="f_bhk">{{ __('BHK') }}</label>
                            <select id="f_bhk" name="bhk" class="input"><option value="">{{ __('Any') }}</option>@foreach ([1, 2, 3, 4, 5] as $b)<option value="{{ $b }}" @selected($f['bhk_min'] === $b)>{{ $b }}{{ $b === 5 ? '+' : '' }} {{ __('BHK') }}</option>@endforeach</select></div>
                        @if ($homeTypes)
                            <div><label class="label" for="f_furn">{{ __('Furnishing') }}</label>
                                <select id="f_furn" name="furnishing" class="input"><option value="">{{ __('Any') }}</option>@foreach ($PS::FURNISHING as $k => $l)<option value="{{ $k }}" @selected($f['furnishing'] === $k)>{{ __($l) }}</option>@endforeach</select></div>
                        @endif
                        <div><p class="label">{{ __('Built-up area (sq ft)') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <input name="area_min" type="number" min="0" step="50" class="input" placeholder="{{ __('Min') }}" aria-label="{{ __('Minimum area') }}" value="{{ $f['area_min'] ? (int) $f['area_min'] : '' }}">
                                <input name="area_max" type="number" min="0" step="50" class="input" placeholder="{{ __('Max') }}" aria-label="{{ __('Maximum area') }}" value="{{ $f['area_max'] ? (int) $f['area_max'] : '' }}">
                            </div></div>
                        <div><label class="label" for="f_facing">{{ __('Facing') }}</label>
                            <select id="f_facing" name="facing" class="input"><option value="">{{ __('Any') }}</option>@foreach ($PS::FACINGS as $x)<option value="{{ $x }}" @selected($f['facing'] === $x)>{{ __($x) }}</option>@endforeach</select></div>
                    @endif

                    <div><label class="label" for="f_feature">{{ __('Must have') }}</label>
                        <input id="f_feature" name="feature" class="input" value="{{ implode(', ', $f['features']) }}" placeholder="{{ $isRent ? __('e.g. Car parking, Lift') : ($activeType === 'farm_land' ? __('e.g. EB connection') : __('e.g. Gated community, Borewell')) }}"></div>
                    @unless ($isRent)
                        <label class="flex items-center gap-2"><input type="checkbox" name="approved" value="1" class="checkbox" @checked($f['approved'])> {{ $context === 'layouts' ? __('With approval number only') : __('Approved / RERA only') }}</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="offer" value="1" class="checkbox" @checked($f['offer'])> {{ __('On offer') }}</label>
                    @endunless
                    <div><label class="label" for="f_sort">{{ __('Sort by') }}</label>
                        <select id="f_sort" name="sort" class="input">@foreach ($PS::SORTS as $k => $l)<option value="{{ $k }}" @selected($sort === $k)>{{ __($l) }}</option>@endforeach</select></div>
                    <div class="flex gap-2"><button class="btn-primary flex-1">{{ __('Apply') }}</button><a href="{{ $formAction }}" class="btn-ghost">{{ __('Clear') }}</a></div>
                </form>
            </details>
        </aside>

        <div class="min-w-0">
            @if ($total === 0)
                <div class="card card-pad text-center">
                    <p class="text-lg font-extrabold">{{ __('No properties match yet') }}</p>
                    <p class="mt-1 text-muted">{{ __('Try removing a filter, or save this search and we will email you when something matching is listed.') }}</p>
                </div>
            @elseif ($view === 'map')
                <div class="h-[70vh] min-h-[420px] overflow-hidden rounded-2xl ring-1 ring-navy-50" data-map-pins="search-pins" role="application" aria-label="{{ __('Map of results') }}"></div>
                <script type="application/json" id="search-pins" nonce="{{ $cspNonce ?? '' }}">{!! json_encode($pins, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
                <p class="mt-2 text-sm text-muted">{{ __(':shown of :total results have a map location.', ['shown' => $pins->count(), 'total' => $total]) }} <a href="{{ $viewUrl('list') }}">{{ __('See all as a list') }}</a>.</p>
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
            <p class="text-sm">{!! __(':count selected · compare up to 3', ['count' => '<strong data-compare-count>0</strong>']) !!}</p>
            <a href="{{ route('market.compare') }}" class="btn-primary btn-sm" data-compare-go>{{ __('Compare') }}</a>
        </div>
    </div>
</x-layouts.public>
