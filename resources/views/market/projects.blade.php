<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
            <nav class="text-sm text-muted" aria-label="{{ __('Breadcrumb') }}"><a href="{{ route('home') }}">{{ __('Home') }}</a> / <span aria-current="page">{{ __('Projects') }}</span></nav>
            <h1 class="mt-2 text-4xl font-extrabold tracking-tight">{{ __('Approved plot layouts') }}</h1>
            <form method="GET" class="mt-6 grid gap-3 rounded-2xl bg-page p-4 sm:grid-cols-2 lg:grid-cols-5" role="search">
                <div class="lg:col-span-2"><label for="p_q" class="mb-1 block text-xs font-semibold text-muted">{{ __('Search') }}</label><input id="p_q" name="q" value="{{ request('q') }}" class="input" placeholder="{{ __('Project, town or district') }}"></div>
                <div><label for="p_loc" class="mb-1 block text-xs font-semibold text-muted">{{ __('Location') }}</label><select id="p_loc" name="location" class="input"><option value="">{{ __('Anywhere') }}</option>@foreach ($locations as $l)<option @selected(request('location') === $l)>{{ $l }}</option>@endforeach</select></div>
                <div><label for="p_budget" class="mb-1 block text-xs font-semibold text-muted">{{ __('Budget up to (₹)') }}</label><input id="p_budget" name="budget" type="number" min="0" step="50000" value="{{ request('budget') }}" class="input" placeholder="1500000"></div>
                <div><label for="p_size" class="mb-1 block text-xs font-semibold text-muted">{{ __('Plot size (sq ft)') }}</label><input id="p_size" name="size" type="number" min="0" step="100" value="{{ request('size') }}" class="input" placeholder="1200"></div>
                <div class="flex flex-wrap items-center gap-2 lg:col-span-5">
                    @foreach (['' => 'All approvals', 'Village' => 'Village', 'Town' => 'Town', 'City' => 'City'] as $v => $l)
                        <label class="cursor-pointer rounded-full px-4 py-1.5 text-sm font-semibold ring-1 ring-navy-100 has-[:checked]:bg-navy has-[:checked]:text-white"><input type="radio" name="type" value="{{ $v }}" class="sr-only" @checked(request('type', '') === $v)>{{ $l }}</label>
                    @endforeach
                    <button class="btn-primary btn-pill ml-auto px-6">{{ __('Show plots') }}</button>
                </div>
            </form>
        </div>
    </section>
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <p class="mb-4 text-sm text-muted">{{ trans_choice(':count layout|:count layouts', $projects->count()) }}</p>
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($projects as $p)
                @php($avail = $p->plots->where('status', 'available'))
                <article class="card overflow-hidden reveal">
                    <a href="{{ $p->publicUrl() }}" class="block bg-page">
                        @if ($p->layoutFile && str_starts_with($p->layoutFile->mime, 'image/'))
                            <img src="{{ $p->layoutUrl() }}" alt="{{ __('Layout plan of :name', ['name' => $p->name]) }}" class="aspect-[16/10] w-full object-cover" width="{{ $p->layout_width ?: 800 }}" height="{{ $p->layout_height ?: 500 }}" loading="lazy" decoding="async">
                        @else
                            <div class="grid aspect-[16/10] grid-cols-10 gap-1 p-4" aria-hidden="true">@foreach ($p->plots->take(60) as $pl)<span class="rounded-sm {{ ['available' => 'bg-teal-600', 'booked' => 'bg-amber-400', 'blocked' => 'bg-red-300'][$pl->marketStatus()] }}"></span>@endforeach</div>
                        @endif
                    </a>
                    <div class="p-5">
                        <h2 class="text-xl font-extrabold"><a href="{{ $p->publicUrl() }}" class="text-navy no-underline hover:underline">{{ $p->name }}</a></h2>
                        <p class="text-sm text-muted">{{ $p->location }}, {{ $p->district }} · {{ __(':type approved', ['type' => __($p->approval_type)]) }}</p>
                        <div class="mt-4 flex items-end justify-between">
                            <div><p class="text-xs text-muted">{{ __('Plots from') }}</p><p class="text-2xl font-extrabold text-teal-700">{{ $avail->isNotEmpty() ? \App\Support\Format::inrShort($p->minPrice()) : '—' }}</p></div>
                            <div class="text-right text-sm"><p class="font-bold">{{ __(':n of :total', ['n' => $avail->count(), 'total' => $p->plots->count()]) }}</p><p class="text-muted">{{ __('available') }}</p></div>
                        </div>
                        @if ($avail->filter->offerIsActive()->isNotEmpty())<p class="mt-3 inline-flex rounded-md bg-gold px-2 py-0.5 text-xs font-extrabold text-navy">{{ __('Offers running') }}</p>@endif
                    </div>
                </article>
            @empty
                <div class="card md:col-span-2 xl:col-span-3"><x-empty :title="__('No layouts match these filters')" icon="search">{{ __('Try a wider budget or another location — or') }} <a href="{{ route('market.requirement') }}">{{ __('describe what you want') }}</a> {{ __('and we will email you when it is launched.') }}</x-empty></div>
            @endforelse
        </div>
    </section>
</x-layouts.public>
