<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    @php($available = $plots->where('status', 'available'))
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <nav class="text-sm text-muted" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('market.projects') }}">Projects</a> / <span aria-current="page">{{ $project->name }}</span></nav>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-6">
                <div>
                    <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $project->name }}</h1>
                    <p class="mt-2 text-lg text-muted">{{ $project->location }}, {{ $project->district }}, {{ $project->state }} · {{ $project->approval_type }} approved · {{ (float) $project->size_acres }} acres</p>
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
        <div class="space-y-8 lg:col-span-2">
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

            @if ($gallery->isNotEmpty())
                <section aria-labelledby="gal-h"><h2 id="gal-h" class="text-xl font-extrabold">Gallery</h2>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($gallery as $img)<a href="{{ route('market.gallery', [$project->slug, $img]) }}" target="_blank" rel="noopener"><img src="{{ route('market.gallery', [$project->slug, $img]) }}" alt="{{ $project->name }} photo {{ $loop->iteration }}" class="aspect-[4/3] w-full rounded-xl object-cover" loading="lazy" decoding="async" width="600" height="450"></a>@endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            @if (! empty($project->facilities))
                <section class="card card-pad"><h2 class="font-extrabold">Facilities</h2>
                    <ul class="mt-3 space-y-2 text-sm">@foreach ($project->facilities as $f)<li class="flex gap-2"><span class="text-teal-700">{!! \App\Support\Icons::svg('check', 'h-4 w-4 mt-0.5') !!}</span>{{ $f }}</li>@endforeach</ul>
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
                @if ($project->map_url)<a href="{{ $project->map_url }}" class="btn-light btn-sm mt-3" target="_blank" rel="noopener nofollow"><x-icon name="location" class="h-4 w-4" /> Open in maps</a>@endif
            </section>
            <section class="card card-pad">
                <div class="flex items-center gap-3">
                    @if ($tenant->logo_path)<img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->name }}" class="h-12 w-12 rounded-xl object-contain ring-1 ring-navy-50" width="48" height="48" loading="lazy">@endif
                    <div><p class="text-xs text-muted">Promoter</p><p class="font-extrabold">{{ $tenant->name }}</p></div>
                </div>
                <p class="mt-2 text-sm text-muted">{{ $tenant->city }}@if ($project->contact ?: $tenant->contact) · <a href="tel:+91{{ $project->contact ?: $tenant->contact }}">{{ $project->contact ?: $tenant->contact }}</a>@endif</p>
                <form method="POST" action="{{ route('market.enquiry') }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="project_id" value="{{ $project->id }}">
                    <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                    <p class="font-semibold">Ask about this project</p>
                    <x-field name="name" label="Your name" :value="auth('customer')->user()?->name" required id="e_name" />
                    <x-field name="email" type="email" label="Email" :value="auth('customer')->user()?->email" required id="e_email" />
                    <x-field name="mobile" type="tel" label="Mobile" :value="auth('customer')->user()?->mobile" required inputmode="numeric" maxlength="10" id="e_mobile" />
                    <x-textarea name="message" label="Message" rows="3" required :value="'I would like to know more about '.$project->name.'.'" id="e_msg" />
                    <button class="btn-primary w-full">Send enquiry</button>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.public>
