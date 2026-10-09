<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <nav class="text-sm text-muted" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('market.projects') }}">Projects</a> / <a href="{{ $project->publicUrl() }}">{{ $project->name }}</a> / <span aria-current="page">Plot {{ $plot->plot_no }}</span></nav>
        <div class="mt-4 grid gap-8 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <div class="flex flex-wrap items-start gap-4">
                    <h1 class="text-5xl font-extrabold tracking-tight sm:text-6xl">Plot {{ $plot->plot_no }}</h1>
                    <x-status :status="$plot->status" class="mt-3" />
                    @if ($plot->offerIsActive())<span class="mt-3 rounded-md bg-gold px-2 py-0.5 text-xs font-extrabold text-navy">OFFER</span>@endif
                </div>
                <p class="mt-2 text-lg text-muted">{{ $project->name }} · {{ $project->location }}, {{ $project->district }}</p>
                <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                    @foreach ([
                        ['Size', \App\Support\Format::num($plot->size_sqft).' sq ft', $plot->cents().' cents'],
                        ['Dimensions', $plot->dimensions() ?? '—', null],
                        ['Facing', $plot->facing, null],
                        ['Patta number', $plot->patta_number, null],
                        ['Road width', $plot->road_width_ft ? (float) $plot->road_width_ft.' ft' : '—', null],
                        ['Corner plot', $plot->is_corner ? 'Yes' : 'No', null],
                    ] as [$k, $v, $sub])
                        <div><dt class="text-sm text-muted">{{ $k }}</dt><dd class="text-lg font-bold">{{ $v }}@if ($sub)<span class="block text-sm font-normal text-muted">{{ $sub }}</span>@endif</dd></div>
                    @endforeach
                </dl>
                <h2 class="mt-10 text-lg font-extrabold">Boundaries</h2>
                <div class="mt-3 grid max-w-md grid-cols-3 grid-rows-3 gap-2 text-center text-sm">
                    <div></div><div class="rounded-lg bg-white p-2 shadow-card"><span class="block text-xs text-muted">North</span>{{ $plot->north_boundary ?: '—' }}</div><div></div>
                    <div class="rounded-lg bg-white p-2 shadow-card"><span class="block text-xs text-muted">West</span>{{ $plot->west_boundary ?: '—' }}</div>
                    <div class="flex items-center justify-center rounded-lg bg-navy font-extrabold text-white">{{ $plot->plot_no }}</div>
                    <div class="rounded-lg bg-white p-2 shadow-card"><span class="block text-xs text-muted">East</span>{{ $plot->east_boundary ?: '—' }}</div>
                    <div></div><div class="rounded-lg bg-white p-2 shadow-card"><span class="block text-xs text-muted">South</span>{{ $plot->south_boundary ?: '—' }}</div><div></div>
                </div>
                @if ($project->layoutUrl())
                    <h2 class="mt-10 text-lg font-extrabold">Where it is in the layout</h2>
                    <div class="mt-3">@include('partials.plot-map', ['plots' => $project->plots()->where('status', '!=', 'sold')->get()->each->setRelation('project', $project), 'layoutUrl' => $project->layoutUrl()])</div>
                @endif
            </div>
            <aside class="lg:col-span-2">
                <div class="card card-pad lg:sticky lg:top-24">
                    @include('partials.plot-price', ['plot' => $plot, 'size' => 'lg'])
                    @if ($plot->status === 'available')
                        <a href="{{ route('account.book', $plot) }}" class="btn-primary btn-pill mt-6 w-full py-3.5 text-base">Book this plot</a>
                        <p class="mt-2 text-center text-xs text-muted">Booking is free. You pay the promoter directly in instalments.</p>
                    @else
                        <p class="mt-6 rounded-xl bg-page p-4 text-sm">This plot is {{ strtolower($plot->statusLabel()) }}. <a href="{{ $project->publicUrl() }}">See available plots</a>.</p>
                    @endif
                    @if (! empty($project->facilities))
                        <p class="mt-6 text-sm font-bold">Facilities</p>
                        <p class="mt-1 text-sm text-muted">{{ implode(' · ', $project->facilities) }}</p>
                    @endif
                    <p class="mt-6 text-sm"><span class="text-muted">Promoter</span> <strong>{{ $project->tenantRel->name }}</strong></p>
                    @if ($project->map_url)<a href="{{ $project->map_url }}" class="btn-light btn-sm mt-3" target="_blank" rel="noopener nofollow"><x-icon name="location" class="h-4 w-4" /> Location map</a>@endif
                </div>
            </aside>
        </div>
        @if ($gallery->isNotEmpty())
            <h2 class="mt-12 text-xl font-extrabold">Gallery</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach ($gallery as $img)<img src="{{ route('market.gallery', [$project->slug, $img]) }}" alt="{{ $project->name }} photo {{ $loop->iteration }}" class="aspect-[4/3] w-full rounded-xl object-cover" loading="lazy" width="400" height="300">@endforeach</div>
        @endif
        @if ($similar->isNotEmpty())
            <h2 class="mt-12 text-xl font-extrabold">Similar plots in {{ $project->name }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach ($similar as $s)@include('partials.plot-card', ['plot' => $s])@endforeach</div>
        @endif
    </div>
</x-layouts.public>
