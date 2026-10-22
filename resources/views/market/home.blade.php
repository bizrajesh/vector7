<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    {{-- Hero: headline + search on the left, featured launches climbing the stepped "7" on the right --}}
    <section class="relative overflow-hidden bg-white">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-12 lg:py-20">
            <div class="lg:col-span-6">
                <h1 class="text-[2.4rem] font-extrabold leading-[1.05] tracking-tight text-navy sm:text-6xl">{{ __('Find your place') }}<br>{{ __('in Tamil Nadu.') }}</h1>
                <p class="mt-5 max-w-xl text-lg text-muted">{{ __('Approved plots, flats, villas, houses, farm land and shops — listed by verified promoters and owners, with real photos and today\'s price.') }}</p>
                <form action="{{ route('market.search') }}" method="GET" class="mt-8 rounded-2xl bg-page p-3 ring-1 ring-navy-50 sm:p-4" role="search" aria-label="{{ __('Search properties') }}">
                    <label for="h_q" class="mb-1 block text-sm font-semibold">{{ __('Tell us what you are looking for — in English or தமிழ்') }}</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input id="h_q" name="q" class="input flex-1 py-3 text-base" maxlength="500" required placeholder="{{ __('2 BHK flat in Thanjavur under 50 lakh near a school') }}">
                        <button class="btn-primary btn-pill px-7 py-3"><x-icon name="sparkles" class="h-4 w-4" /> {{ __('Search') }}</button>
                    </div>
                    <p class="mt-3 flex flex-wrap gap-2 text-sm">
                        <span class="text-muted">{{ __('Try:') }}</span>
                        @foreach (['DTCP plot near Vallam under 15 lakh', 'Villa with car parking in Trichy', '2 acres farm land with borewell', 'Shop for rent in Thanjavur'] as $ex)
                            <a href="{{ route('market.search', ['q' => __($ex)]) }}" class="chip chip-sm no-underline">{{ __($ex) }}</a>
                        @endforeach
                    </p>
                </form>
                <details class="mt-3 rounded-2xl bg-page ring-1 ring-navy-50">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold"><x-icon name="filter" class="mr-1 inline h-4 w-4" /> {{ __('Or use classic filters') }}</summary>
                    <form action="{{ route('market.search') }}" method="GET" class="grid gap-3 px-4 pb-4 sm:grid-cols-4" aria-label="{{ __('Classic property filters') }}">
                        <div><label for="hc_type" class="mb-1 block text-xs font-semibold text-muted">{{ __('Type') }}</label>
                            <select id="hc_type" name="type[]" class="input"><option value="">{{ __('Any') }}</option><option value="layout">{{ __('Layouts & plots') }}</option>@foreach (\App\Listings\ListingTypes::all() as $k => $t)<option value="{{ $k }}">{{ $t->plural() }}</option>@endforeach</select></div>
                        <div><label for="hc_place" class="mb-1 block text-xs font-semibold text-muted">{{ __('City / district') }}</label>
                            <input id="hc_place" name="place" class="input" list="hc_places" placeholder="{{ __('Anywhere') }}"><datalist id="hc_places">@foreach ($places as $p)<option value="{{ $p }}">@endforeach</datalist></div>
                        <div><label for="hc_budget" class="mb-1 block text-xs font-semibold text-muted">{{ __('Budget up to') }}</label>
                            <select id="hc_budget" name="budget_max" class="input"><option value="">{{ __('Any') }}</option>@foreach ([1000000 => '₹10 lakh', 2500000 => '₹25 lakh', 5000000 => '₹50 lakh', 10000000 => '₹1 crore', 20000000 => '₹2 crore'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                        <div><label for="hc_area" class="mb-1 block text-xs font-semibold text-muted">{{ __('Min area (sq ft)') }}</label>
                            <input id="hc_area" name="area_min" type="number" min="0" step="100" class="input"></div>
                        <button class="btn-primary sm:col-span-4">{{ __('Search') }}</button>
                    </form>
                </details>
                <dl class="mt-8 flex flex-wrap gap-x-10 gap-y-3 text-sm">
                    <div><dt class="text-muted">{{ __('Properties listed') }}</dt><dd class="text-2xl font-extrabold">{{ number_format($stats['listings']) }}</dd></div>
                    <div><dt class="text-muted">{{ __('Launched layouts') }}</dt><dd class="text-2xl font-extrabold">{{ $stats['projects'] }}</dd></div>
                    <div><dt class="text-muted">{{ __('Plots available today') }}</dt><dd class="text-2xl font-extrabold">{{ number_format($stats['available']) }}</dd></div>
                </dl>
            </div>

            <div class="relative lg:col-span-6">
                {{-- the stepped 7, from the logo mark --}}
                <div class="pointer-events-none absolute -right-6 -top-8 hidden w-[78%] flex-col items-end gap-3 lg:flex" aria-hidden="true">
                    <span class="h-6 w-full rounded-sm bg-navy"></span>
                    @foreach (['w-[26%] bg-teal-700', 'w-[26%] bg-teal-600 mr-[11%]', 'w-[26%] bg-teal-500 mr-[22%]', 'w-[26%] bg-teal-200 mr-[33%]', 'w-[26%] bg-teal-100 mr-[44%]'] as $c)
                        <span class="h-4 rounded-sm {{ $c }}"></span>
                    @endforeach
                </div>
                @if ($featured->isNotEmpty())
                    <div class="carousel relative mt-0 rounded-3xl bg-white shadow-lift ring-1 ring-navy-50 lg:mt-24" data-carousel aria-roledescription="carousel" aria-label="{{ __('Featured launches') }}">
                        <div class="carousel-track">
                            @foreach ($featured as $i => $p)
                                @php($avail = $p->plots->where('status', 'available'))
                                <div class="carousel-slide p-4 sm:p-5" role="group" aria-roledescription="slide" aria-label="{{ __(':n of :total', ['n' => $i + 1, 'total' => $featured->count()]) }}">
                                    <a href="{{ $p->publicUrl() }}" class="block overflow-hidden rounded-2xl bg-page">
                                        @if ($p->images->isNotEmpty())
                                            <img src="{{ $p->images->first()->url() }}" alt="{{ $p->name }}" class="aspect-[4/3] w-full object-cover" width="800" height="600" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                                        @elseif ($p->layoutFile && str_starts_with($p->layoutFile->mime, 'image/'))
                                            <img src="{{ $p->layoutUrl() }}" alt="{{ __('Layout plan of :name', ['name' => $p->name]) }}" class="aspect-[4/3] w-full object-cover" width="{{ $p->layout_width ?: 800 }}" height="{{ $p->layout_height ?: 600 }}" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                                        @else
                                            <div class="grid aspect-[4/3] grid-cols-8 gap-1 p-4">@foreach ($p->plots->take(48) as $pl)<span class="rounded-sm {{ ['available' => 'bg-teal-600', 'booked' => 'bg-amber-400', 'sold' => 'bg-slate-400', 'blocked' => 'bg-red-300'][$pl->marketStatus()] }}"></span>@endforeach</div>
                                        @endif
                                    </a>
                                    <div class="mt-4 flex flex-wrap items-end justify-between gap-3 px-1">
                                        <div>
                                            <p class="text-xl font-extrabold"><a href="{{ $p->publicUrl() }}" class="text-navy no-underline hover:underline">{{ $p->name }}</a></p>
                                            <p class="text-sm text-muted">{{ $p->location }}, {{ $p->district }} · {{ __(':type approved', ['type' => __($p->approval_type)]) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-muted">{{ __('From') }}</p>
                                            <p class="text-xl font-extrabold text-teal-700">{{ \App\Support\Format::inrShort($p->minPrice()) }}</p>
                                            <p class="text-xs font-semibold">{{ trans_choice(':count plot available|:count plots available', $avail->count()) }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($featured->count() > 1)
                            <div class="flex items-center justify-between px-5 pb-5">
                                <div class="flex gap-1.5">@foreach ($featured as $i => $p)<button type="button" class="h-2 w-6 rounded-full bg-navy-100" data-dot aria-label="{{ __('Show :name', ['name' => $p->name]) }}"></button>@endforeach</div>
                                <div class="flex gap-1">
                                    <button type="button" class="btn-light btn-sm" data-prev aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" class="h-4 w-4" /></button>
                                    <button type="button" class="btn-light btn-sm" data-next aria-label="{{ __('Next') }}"><x-icon name="chevron-right" class="h-4 w-4" /></button>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-3xl bg-page p-10 text-center lg:mt-24">
                        <p class="text-xl font-extrabold">{{ __('New layouts are on their way') }}</p>
                        <p class="mt-2 text-muted">{{ __('Save what you are looking for and we will email you when a matching plot is launched.') }}</p>
                        <a href="{{ route('market.search') }}" class="btn-primary btn-pill mt-5">{{ __('Search properties') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Categories --}}
    <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6" aria-labelledby="cat-h">
        <h2 id="cat-h" class="text-3xl font-extrabold tracking-tight">{{ __('Browse by property type') }}</h2>
        <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            @foreach ($categories as $c)
                <li><a href="{{ $c['url'] }}" class="card card-pad block h-full text-navy no-underline transition hover:shadow-lift">
                    <span class="text-teal-700"><x-icon :name="$c['icon']" class="h-7 w-7" /></span>
                    <span class="mt-3 block font-bold">{{ $c['label'] }}</span>
                    <span class="text-sm text-muted">{{ trans_choice(':count listing|:count listings', $c['count']) }}</span>
                </a></li>
            @endforeach
        </ul>
    </section>

    @foreach ([['feat-h', 'Featured', $listings, route('market.category', ['purpose' => 'buy'])], ['lay-h', 'New layouts launched', $newLayouts, \App\Services\PropertySearch::categoryUrl('layout')],
        ['off-h', 'Best offers ending soon', $offers, route('market.search', ['offer' => 1])], ['rec-h', 'Recently added', $recent, route('market.search', ['sort' => 'newest'])]] as [$hid, $heading, $set, $more])
        @if ($set->isNotEmpty())
            <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6" aria-labelledby="{{ $hid }}">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 id="{{ $hid }}" class="text-3xl font-extrabold tracking-tight">{{ __($heading) }}</h2>
                    <a href="{{ $more }}" class="font-semibold">{{ __('See all') }}</a>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($set as $item)@include('partials.property-card', ['item' => $item, 'shortlisted' => []])@endforeach
                </div>
            </section>
        @endif
    @endforeach

    @if ($districts->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pt-14 sm:px-6" aria-labelledby="dist-h">
            <h2 id="dist-h" class="text-3xl font-extrabold tracking-tight">{{ __('Browse by district') }}</h2>
            <ul class="mt-6 flex flex-wrap gap-2">
                @foreach ($districts as $d => $n)
                    <li><a href="{{ route('market.search', ['place' => $d]) }}" class="chip no-underline hover:bg-white">{{ $d }} <span class="text-muted">{{ $n }}</span></a></li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- How it works: a real sequence --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <h2 class="max-w-2xl text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('From a plot you like to a registered sale deed') }}</h2>
        <ol class="mt-10 grid gap-6 md:grid-cols-4">
            @foreach ([
                ['Pick a plot on the live map', 'Every plot shows its size, facing, boundaries and today\'s price — offers included.'],
                ['Book it online', 'Booking is free. The plot is held for you for the promoter\'s booking period while you pay the first instalment.'],
                ['Pay in instalments', 'Pay the promoter by bank transfer, UPI or cheque. Every payment gets a numbered receipt in your account.'],
                ['Register', 'The promoter prepares the registration pack and the sale deed is registered at your SRO.'],
            ] as $i => [$h, $t])
                <li class="reveal">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-navy text-lg font-extrabold text-white">{{ $i + 1 }}</span>
                    <h3 class="mt-4 text-lg font-bold">{{ __($h) }}</h3>
                    <p class="mt-1 text-muted">{{ __($t) }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Trust --}}
    <section class="bg-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('Fewer surprises at the site visit') }}</h2>
                <p class="mt-4 text-lg text-muted">{{ __('Promoters on vector7 track every approval stage — land records, layout approval, gift deed, RERA — before a layout can be launched. What you see is what has been approved.') }}</p>
            </div>
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ([['shield', 'Approvals tracked', 'Layouts go live only after their approval stages are complete.'], ['map', 'Live plot status', 'Booked and sold plots are marked the moment it happens.'], ['receipt', 'Receipts for every rupee', 'Numbered PDF receipts in your account, always.'], ['doc', 'Documents in one place', 'Registration pack, acknowledgement and receipts to download any time.'], ['check', 'Approval & RERA badges', 'Approval and RERA numbers are shown on the listing when the promoter has them.'], ['alert', 'Report anything wrong', 'Every listing has a Report button. The vector7 team reviews reports and hides fake listings.']] as [$icon, $h, $t])
                    <li class="rounded-2xl bg-page p-5"><span class="text-teal-700"><x-icon :name="$icon" class="h-6 w-6" /></span><p class="mt-3 font-bold">{{ __($h) }}</p><p class="mt-1 text-sm text-muted">{{ __($t) }}</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($testimonials->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6" aria-labelledby="tst-h">
            <h2 id="tst-h" class="text-3xl font-extrabold tracking-tight">{{ __('What buyers say') }}</h2>
            <div class="mt-6 grid gap-4 md:grid-cols-3">
                @foreach ($testimonials as $t)
                    <figure class="card card-pad">
                        @if ($t->rating)<p class="text-gold" aria-label="{{ __(':n out of 5', ['n' => $t->rating]) }}">{{ str_repeat('★', $t->rating) }}<span class="text-navy-100">{{ str_repeat('★', 5 - $t->rating) }}</span></p>@endif
                        <blockquote class="mt-2 leading-relaxed">“{{ $t->body }}”</blockquote>
                        <figcaption class="mt-3 text-sm font-semibold">{{ \Illuminate\Support\Str::before($t->name, ' ') ?: $t->name }}@if ($t->place)<span class="font-normal text-muted">, {{ $t->place }}</span>@endif</figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    @if ($services->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-3xl font-extrabold tracking-tight">{{ __('Help with the paperwork') }}</h2>
                <a href="{{ route('market.services') }}" class="font-semibold">{{ __('All services') }}</a>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($services as $s)
                    <a href="{{ route('market.service', $s->slug) }}" class="card card-pad block text-navy no-underline transition hover:shadow-lift">
                        <p class="font-bold">{{ $s->name }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $s->summary }}</p>
                        <p class="mt-3 text-sm font-semibold text-teal-700">{{ $s->priceLabel() }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="flex flex-col items-start justify-between gap-6 rounded-3xl bg-navy p-8 text-white sm:p-12 lg:flex-row lg:items-center">
            <div class="max-w-2xl">
                <h2 class="text-3xl font-extrabold tracking-tight text-white">{{ __('Promoter or owner? List on vector7') }}</h2>
                <ol class="mt-4 grid gap-2 text-sm text-navy-100 sm:grid-cols-3"><li><strong class="text-white">1.</strong> {{ __('Create a free workspace') }}</li><li><strong class="text-white">2.</strong> {{ __('Quick-launch a layout or post a property with photos') }}</li><li><strong class="text-white">3.</strong> {{ __('Calls, WhatsApp and enquiries arrive in Leads') }}</li></ol>
                <p class="mt-3 text-navy-100">{{ __('Post plots, flats, villas, houses, farm land or shops with photos and a map pin. Every call, WhatsApp and enquiry lands in your Leads. Start on a free trial.') }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if (\App\Support\Signups::open('promoter'))
                <a href="{{ route('signup') }}" class="btn btn-pill bg-gold px-7 py-3 text-navy hover:bg-amber-300 hover:text-navy">{{ __('Create a workspace') }}</a>
                @else
                <a href="{{ route('staff.login') }}" class="btn btn-pill bg-gold px-7 py-3 text-navy hover:bg-amber-300 hover:text-navy">{{ __('Promoter sign in') }}</a>
                @endif
                <a href="{{ route('market.pricing') }}" class="btn btn-pill px-7 py-3 text-white ring-1 ring-white/30 hover:bg-white/10 hover:text-white">{{ __('See plans') }}</a>
            </div>
        </div>
    </section>
</x-layouts.public>
