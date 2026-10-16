<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    @if ($project->lat !== null && $project->lng !== null)@include('partials.leaflet')@endif
    @php($available = $plots->where('status', 'available'))
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <nav class="text-sm text-muted" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('market.projects') }}">Projects</a> / <span aria-current="page">{{ $project->name }}</span></nav>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-6">
                <div>
                    <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $project->name }}</h1>
                    <p class="mt-2 text-lg text-muted">{{ collect([$project->village, $project->location, $project->district, $project->state])->filter()->unique()->implode(', ') }} · {{ $project->approval_type }} approved · {{ (float) $project->size_acres }} acres</p>
                    @if ($project->approvalBadge())<p class="mt-2"><span class="badge-teal"><x-icon name="shield" class="inline h-3.5 w-3.5" /> {{ $project->approvalBadge() }}</span></p>@endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-muted">Plots from</p>
                    <p class="text-3xl font-extrabold text-teal-700">{{ $available->isNotEmpty() ? \App\Support\Format::inr($available->min(fn ($p) => $p->currentPrice())) : 'Sold out' }}</p>
                    <p class="text-sm font-semibold">{{ $available->count() }} plots available now</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-8 lg:col-span-2">
            @if ($images->isNotEmpty())
                <section aria-label="Photos">
                    @php($cover = $images->first())
                    <a href="{{ $cover->url() }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-2xl bg-page"><img src="{{ $cover->url() }}" alt="{{ $cover->caption ?: $project->name }}" class="aspect-[16/9] w-full object-cover" width="{{ $cover->width ?: 1600 }}" height="{{ $cover->height ?: 900 }}" fetchpriority="high"></a>
                    @if ($images->count() > 1)
                        <ul class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-6">@foreach ($images->skip(1) as $img)<li><a href="{{ $img->url() }}" target="_blank" rel="noopener"><img src="{{ $img->url(true) }}" alt="{{ $img->caption ?: $project->name.' photo '.$loop->iteration }}" class="aspect-[4/3] w-full rounded-lg object-cover" loading="lazy" width="400" height="300"></a></li>@endforeach</ul>
                    @endif
                </section>
            @endif
            <section class="card card-pad" aria-labelledby="map-h">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="map-h" class="text-xl font-extrabold">Plot map</h2>
                    <p class="text-sm text-muted">Hover or tap a plot for details</p>
                </div>
                <div class="mt-4">@include('partials.plot-map', ['plots' => $plots, 'layoutUrl' => $project->layoutUrl()])</div>
            </section>

            <section aria-labelledby="plots-h">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="plots-h" class="text-2xl font-extrabold">Plots</h2>
                    <p class="text-sm font-semibold" data-plot-count aria-live="polite">{{ $plots->count() }} plots</p>
                </div>
                <form class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-white p-4 shadow-card sm:grid-cols-3 lg:grid-cols-6" data-plot-filters aria-label="Filter plots">
                    <div><label class="mb-1 block text-xs font-semibold text-muted" for="f_min">Min sq ft</label><input id="f_min" name="min_size" type="number" class="input" min="0" step="100"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-muted" for="f_max">Max sq ft</label><input id="f_max" name="max_size" type="number" class="input" min="0" step="100"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-muted" for="f_facing">Facing</label><select id="f_facing" name="facing" class="input"><option value="">Any</option>@foreach (\App\Models\Plot::FACINGS as $f)<option>{{ $f }}</option>@endforeach</select></div>
                    <div><label class="mb-1 block text-xs font-semibold text-muted" for="f_maxp">Max price ₹</label><input id="f_maxp" name="max_price" type="number" class="input" min="0" step="50000"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-muted" for="f_sort">Sort by</label><select id="f_sort" name="sort" class="input"><option value="plot">Plot number</option><option value="price_asc">Price: low to high</option><option value="price_desc">Price: high to low</option><option value="size_asc">Size: small to large</option><option value="size_desc">Size: large to small</option></select></div>
                    <div><label class="mb-1 block text-xs font-semibold text-muted" for="f_status">Status</label><select id="f_status" name="status" class="input"><option value="">All</option><option value="available" selected>Available</option><option value="booked">Booked</option></select></div>
                    <div class="col-span-2 flex flex-wrap items-center gap-4 sm:col-span-3 lg:col-span-6">
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="corner" class="checkbox"> Corner plots</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="offer" class="checkbox"> On offer</label>
                        <button type="reset" class="btn-ghost btn-sm ml-auto" data-plot-reset>Clear filters</button>
                    </div>
                </form>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-plot-grid>
                    @foreach ($plots as $plot)
                        @include('partials.plot-card', ['plot' => $plot])
                    @endforeach
                </div>
            </section>

            @if ($project->promo_text)
                <section class="card card-pad"><h2 class="text-xl font-extrabold">About {{ $project->name }}</h2><div class="mt-3 max-w-prose whitespace-pre-line leading-relaxed text-navy">{{ $project->promo_text }}</div></section>
            @endif

            @if ($youtubeId)
                <section aria-labelledby="vid-h"><h2 id="vid-h" class="text-xl font-extrabold">Video</h2>
                    <div class="mt-3 aspect-video overflow-hidden rounded-2xl bg-navy"><iframe src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}" title="Video of {{ $project->name }}" class="h-full w-full" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            @include('partials.market-contact', ['kind' => 'project', 'ownerId' => $project->id, 'c' => $contact, 'title' => $project->name, 'src' => $src])
            @if ($project->featureChips())
                <section class="card card-pad"><h2 class="font-extrabold">Features & facilities</h2>
                    <ul class="mt-3 flex flex-wrap gap-2 text-sm">@foreach ($project->featureChips() as $c)<li class="chip"><x-icon :name="$c['icon']" class="h-4 w-4" /> {{ $c['name'] }}</li>@endforeach</ul>
                </section>
            @endif
            @if ($project->stages->isNotEmpty())
                <section class="card card-pad"><h2 class="font-extrabold">Approvals obtained</h2>
                    <ol class="mt-3 space-y-2 text-sm">
                        @foreach ($project->stages as $s)
                            <li class="flex items-center gap-2"><span class="{{ $s->status === 'done' ? 'text-teal-700' : 'text-muted-light' }}">{!! \App\Support\Icons::svg($s->status === 'done' ? 'check' : 'clock', 'h-4 w-4') !!}</span><span class="{{ $s->status === 'done' ? '' : 'text-muted' }}">{{ $s->name }}</span></li>
                        @endforeach
                    </ol>
                </section>
            @endif
            <section class="card card-pad">
                <h2 class="font-extrabold">Location</h2>
                <p class="mt-2 text-sm">{{ $project->address ?: $project->location.', '.$project->district }}</p>
                @if ($project->lat !== null && $project->lng !== null)
                    <div class="mt-3 h-56 overflow-hidden rounded-xl ring-1 ring-navy-50" data-map-pins="project-pin" role="application" aria-label="Map showing the layout location"></div>
                    <script type="application/json" id="project-pin" nonce="{{ $cspNonce ?? '' }}">{!! json_encode([['lat' => (float) $project->lat, 'lng' => (float) $project->lng, 'title' => $project->name, 'type' => 'Layout', 'price' => null, 'url' => null]], JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
                @endif
                @if ($project->map_url)<a href="{{ $project->map_url }}" class="btn-light btn-sm mt-3" target="_blank" rel="noopener nofollow"><x-icon name="location" class="h-4 w-4" /> Open in maps</a>@endif
            </section>
            <section class="card card-pad">
                <div class="flex items-center gap-3">
                    @if ($tenant->logo_path)<img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->name }}" class="h-12 w-12 rounded-xl object-contain ring-1 ring-navy-50" width="48" height="48" loading="lazy">@endif
                    <div><p class="text-xs text-muted">Promoter</p><p class="font-extrabold">{{ $tenant->name }}</p></div>
                </div>
                @if ($tenant->city)<p class="mt-2 text-sm text-muted">{{ $tenant->city }}</p>@endif
                <div class="mt-3 grid gap-2">
                    @auth('customer')
                        <form method="POST" action="{{ route('account.shortlist.toggle') }}">@csrf<input type="hidden" name="key" value="P:{{ $project->id }}"><button class="btn-light w-full"><x-icon name="star" class="h-4 w-4" /> Shortlist this layout</button></form>
                    @endauth
                    @if ($project->brochure)<a href="{{ route('market.brochure', ['project', $project->id]) }}" class="btn-ghost w-full"><x-icon name="download" class="h-4 w-4" /> Download brochure (PDF)</a>@endif
                </div>
            </section>
        </aside>
    </div>
</x-layouts.public>
