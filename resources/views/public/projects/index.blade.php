<x-layouts.public :seo="$seo">
    <section class="bg-navy text-white">
        <div class="mx-auto max-w-6xl px-4 py-10">
            <nav aria-label="Breadcrumb" class="text-sm text-navy-200"><a href="{{ route('home') }}" class="text-navy-200 hover:text-white">Home</a> / <span>Projects</span></nav>
            <h1 class="anim-up mt-3 text-3xl font-extrabold md:text-4xl">Plots for sale in approved layouts</h1>
            <p class="anim-up mt-2 max-w-2xl text-navy-200" style="--d: 100ms">Live availability from each developer. Hold a plot online; the sales team completes the purchase with you.</p>
            <form method="GET" class="anim-up mt-6 flex max-w-lg gap-2" style="--d: 200ms" role="search">
                <label for="pq" class="sr-only">Search projects</label>
                <input id="pq" type="search" name="q" value="{{ request('q') }}" placeholder="Search by project, town or district" class="input border-white/20">
                <button class="btn bg-sage text-navy hover:bg-cream hover:text-navy">Search</button>
            </form>
        </div>
    </section>
    @php
        $term = mb_strtolower(trim((string) request('q')));
        $list = $term === '' ? $layouts : $layouts->filter(fn ($l) => str_contains(mb_strtolower($l->name.' '.$l->location.' '.$l->village.' '.$l->district), $term));
    @endphp
    <div class="mx-auto max-w-6xl px-4 py-10">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($list as $i => $layout)
                <a href="{{ route('projects.show', [$layout->tenant->slug, \Illuminate\Support\Str::lower($layout->code)]) }}" class="anim-up lift card flex flex-col gap-2 p-5 text-ink no-underline hover:text-ink" style="--d: {{ min($i, 8) * 70 }}ms">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-lg font-bold">{{ $layout->name }}</h2>
                        <span class="badge-av">{{ $layout->plots_available }} available</span>
                    </div>
                    <p class="text-sm text-ink-muted">{{ collect([$layout->location, $layout->village, $layout->district])->filter()->implode(' · ') }}</p>
                    @if ($layout->public_summary)<p class="text-sm text-ink-2">{{ \Illuminate\Support\Str::limit($layout->public_summary, 140) }}</p>@endif
                    <div class="progress mt-1"><span style="width: {{ $layout->plots_total ? round(($layout->plots_total - $layout->plots_available) / $layout->plots_total * 100) : 0 }}%"></span></div>
                    <p class="text-xs text-ink-muted">{{ $layout->plots_total - $layout->plots_available }} of {{ $layout->plots_total }} plots taken</p>
                    <div class="mt-auto flex items-end justify-between pt-2">
                        <span class="text-xs text-ink-muted">by {{ $layout->tenant->name }}</span>
                        @if ($layout->price_from)<span class="num font-bold">from @inrShort($layout->price_from)</span>@endif
                    </div>
                </a>
            @empty
                <div class="card sm:col-span-2 lg:col-span-3"><x-empty title="No projects match your search" icon="search">Try another town or clear the search.</x-empty></div>
            @endforelse
        </div>
        <p class="mt-8 text-center text-sm text-ink-muted">Already booked a plot? <a href="{{ route('track') }}" class="font-semibold">Track your booking</a></p>
    </div>
</x-layouts.public>
