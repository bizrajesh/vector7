<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <nav class="text-sm text-muted" aria-label="{{ __('Breadcrumb') }}"><a href="{{ route('home') }}">{{ __('Home') }}</a> / <a href="{{ route('market.projects') }}">{{ __('Projects') }}</a> / <a href="{{ $project->publicUrl() }}">{{ $project->name }}</a> / <span aria-current="page">{{ __('Plot :no', ['no' => $plot->plot_no]) }}</span></nav>
        <div class="mt-4 grid gap-8 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <div class="flex flex-wrap items-start gap-4">
                    <h1 class="text-5xl font-extrabold tracking-tight sm:text-6xl">{{ __('Plot :no', ['no' => $plot->plot_no]) }}</h1>
                    <x-status :status="$plot->marketStatus()" class="mt-3" />
                    @if ($plot->offerIsActive())<span class="mt-3 rounded-md bg-gold px-2 py-0.5 text-xs font-extrabold text-navy">{{ __('OFFER') }}</span>@endif
                </div>
                <p class="mt-2 text-lg text-muted">{{ $project->name }} · {{ $project->location }}, {{ $project->district }}</p>
                <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                    @foreach ([
                        [__('Size'), \App\Support\Format::num($plot->size_sqft).' '.__('sq ft'), $plot->cents().' '.__('cents')],
                        [__('Dimensions'), $plot->dimensions() ?? '—', null],
                        [__('Facing'), __($plot->facing), null],
                        [__('Road width'), $plot->road_width_ft ? (float) $plot->road_width_ft.' '.__('ft') : '—', null],
                        [__('Corner plot'), $plot->is_corner ? __('Yes') : __('No'), null],
                    ] as [$k, $v, $sub])
                        <div><dt class="text-sm text-muted">{{ $k }}</dt><dd class="text-lg font-bold">{{ $v }}@if ($sub)<span class="block text-sm font-normal text-muted">{{ $sub }}</span>@endif</dd></div>
                    @endforeach
                </dl>
                <h2 class="mt-10 text-lg font-extrabold">{{ __('Boundaries') }}</h2>
                <div class="mt-3 grid max-w-md grid-cols-3 grid-rows-3 gap-2 text-center text-sm">
                    <div></div><div class="rounded-lg bg-white p-2 shadow-card"><span class="block text-xs text-muted">{{ __('North') }}</span>{{ $plot->north_boundary ?: '—' }}</div><div></div>
                    <div class="rounded-lg bg-white p-2 shadow-card"><span class="block text-xs text-muted">{{ __('West') }}</span>{{ $plot->west_boundary ?: '—' }}</div>
                    <div class="flex items-center justify-center rounded-lg bg-navy font-extrabold text-white">{{ $plot->plot_no }}</div>
                    <div class="rounded-lg bg-white p-2 shadow-card"><span class="block text-xs text-muted">{{ __('East') }}</span>{{ $plot->east_boundary ?: '—' }}</div>
                    <div></div><div class="rounded-lg bg-white p-2 shadow-card"><span class="block text-xs text-muted">{{ __('South') }}</span>{{ $plot->south_boundary ?: '—' }}</div><div></div>
                </div>
                @if ($project->layoutUrl())
                    <h2 class="mt-10 text-lg font-extrabold">{{ __('Where it is in the layout') }}</h2>
                    <div class="mt-3">@include('partials.plot-map', ['plots' => $project->plots()->orderByRaw('CAST(plot_no AS UNSIGNED), plot_no')->get()->each->setRelation('project', $project), 'layoutUrl' => $project->layoutUrl()])</div>
                @endif
            </div>
            <aside class="lg:col-span-2">
                <div class="card card-pad lg:sticky lg:top-24">
                    @include('partials.plot-price', ['plot' => $plot, 'size' => 'lg'])
                    @if ($plot->status === 'available')
                        <a href="{{ route('account.book', $plot) }}" class="btn-primary btn-pill mt-6 w-full py-3.5 text-base">{{ __('Book this plot') }}</a>
                        <p class="mt-2 text-center text-xs text-muted">{{ __('Booking is free. You pay the promoter directly in instalments.') }}</p>
                    @else
                        <p class="mt-6 rounded-xl bg-page p-4 text-sm">{{ __('This plot is :status.', ['status' => __($plot->marketStatusLabel())]) }} <a href="{{ $project->publicUrl() }}">{{ __('See available plots') }}</a>.</p>
                    @endif
                    @if (! empty($project->facilities))
                        <p class="mt-6 text-sm font-bold">{{ __('Facilities') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ implode(' · ', $project->facilities) }}</p>
                    @endif
                    <p class="mt-6 text-sm"><span class="text-muted">{{ __('Promoter') }}</span> <strong>{{ $project->tenantRel->name }}</strong></p>
                    @if ($project->map_url)<a href="{{ $project->map_url }}" class="btn-light btn-sm mt-3" target="_blank" rel="noopener nofollow"><x-icon name="location" class="h-4 w-4" /> {{ __('Location map') }}</a>@endif
                </div>
            </aside>
        </div>
        @if ($images->isNotEmpty())
            <h2 class="mt-12 text-xl font-extrabold">{{ __('Gallery') }}</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach ($images as $img)<img src="{{ $img->url(true) }}" alt="{{ $img->caption ?: __(':name photo :n', ['name' => $project->name, 'n' => $loop->iteration]) }}" class="aspect-[4/3] w-full rounded-xl object-cover" loading="lazy" width="400" height="300">@endforeach</div>
        @endif
        @if ($similar->isNotEmpty())
            <h2 class="mt-12 text-xl font-extrabold">{{ __('Similar plots in :name', ['name' => $project->name]) }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach ($similar as $s)@include('partials.plot-card', ['plot' => $s])@endforeach</div>
        @endif
    </div>
</x-layouts.public>
