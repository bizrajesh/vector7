<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    {{-- Hero: headline + search on the left, featured launches climbing the stepped "7" on the right --}}
    <section class="relative overflow-hidden bg-white">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-12 lg:py-20">
            <div class="lg:col-span-6">
                <h1 class="text-[2.6rem] font-extrabold leading-[1.05] tracking-tight text-navy sm:text-6xl">Approved plots.<br>Live availability.<br>Book in minutes.</h1>
                <p class="mt-5 max-w-xl text-lg text-muted">Every layout here is listed by its promoter with patta numbers, approvals and today's price — so you see exactly which plots are still free before you visit.</p>
                <form action="{{ route('market.projects') }}" method="GET" class="mt-8 rounded-2xl bg-page p-3 ring-1 ring-navy-50 sm:p-4" role="search" aria-label="Search plots">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="h_loc" class="mb-1 block text-xs font-semibold text-muted">Location</label>
                            <select id="h_loc" name="location" class="input"><option value="">Anywhere</option>@foreach ($locations as $l)<option>{{ $l }}</option>@endforeach</select>
                        </div>
                        <div>
                            <label for="h_budget" class="mb-1 block text-xs font-semibold text-muted">Budget up to</label>
                            <select id="h_budget" name="budget" class="input"><option value="">Any budget</option>@foreach ([500000 => '₹5 lakh', 1000000 => '₹10 lakh', 1500000 => '₹15 lakh', 2500000 => '₹25 lakh', 5000000 => '₹50 lakh', 10000000 => '₹1 crore'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                        </div>
                        <div>
                            <label for="h_size" class="mb-1 block text-xs font-semibold text-muted">Plot size</label>
                            <select id="h_size" name="size" class="input"><option value="">Any size</option>@foreach ([600, 900, 1200, 1500, 2400, 3600] as $s)<option value="{{ $s }}">About {{ number_format($s) }} sq ft</option>@endforeach</select>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <a href="{{ route('market.requirement') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold"><x-icon name="sparkles" class="h-4 w-4" /> Or describe the plot you want in your own words</a>
                        <button class="btn-primary btn-pill px-8 py-3"><x-icon name="search" class="h-4 w-4" /> Search plots</button>
                    </div>
                </form>
                <dl class="mt-8 flex flex-wrap gap-x-10 gap-y-3 text-sm">
                    <div><dt class="text-muted">Launched layouts</dt><dd class="text-2xl font-extrabold">{{ $stats['projects'] }}</dd></div>
                    <div><dt class="text-muted">Plots available today</dt><dd class="text-2xl font-extrabold">{{ number_format($stats['available']) }}</dd></div>
                    <div><dt class="text-muted">Locations</dt><dd class="text-2xl font-extrabold">{{ $stats['locations'] }}</dd></div>
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
                    <div class="carousel relative mt-0 rounded-3xl bg-white shadow-lift ring-1 ring-navy-50 lg:mt-24" data-carousel aria-roledescription="carousel" aria-label="Featured launches">
                        <div class="carousel-track">
                            @foreach ($featured as $i => $p)
                                @php($avail = $p->plots->where('status', 'available'))
                                <div class="carousel-slide p-4 sm:p-5" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $featured->count() }}">
                                    <a href="{{ $p->publicUrl() }}" class="block overflow-hidden rounded-2xl bg-page">
                                        @if ($p->layoutFile && str_starts_with($p->layoutFile->mime, 'image/'))
                                            <img src="{{ $p->layoutUrl() }}" alt="Layout plan of {{ $p->name }}" class="aspect-[4/3] w-full object-cover" width="{{ $p->layout_width ?: 800 }}" height="{{ $p->layout_height ?: 600 }}" @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif>
                                        @else
                                            <div class="grid aspect-[4/3] grid-cols-8 gap-1 p-4">@foreach ($p->plots->take(48) as $pl)<span class="rounded-sm {{ $pl->status === 'available' ? 'bg-teal-600' : ($pl->status === 'blocked' ? 'bg-red-300' : 'bg-navy-100') }}"></span>@endforeach</div>
                                        @endif
                                    </a>
                                    <div class="mt-4 flex flex-wrap items-end justify-between gap-3 px-1">
                                        <div>
                                            <p class="text-xl font-extrabold"><a href="{{ $p->publicUrl() }}" class="text-navy no-underline hover:underline">{{ $p->name }}</a></p>
                                            <p class="text-sm text-muted">{{ $p->location }}, {{ $p->district }} · {{ $p->approval_type }} approved</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-muted">From</p>
                                            <p class="text-xl font-extrabold text-teal-700">{{ \App\Support\Format::inrShort($p->minPrice()) }}</p>
                                            <p class="text-xs font-semibold">{{ $avail->count() }} plots available</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($featured->count() > 1)
                            <div class="flex items-center justify-between px-5 pb-5">
                                <div class="flex gap-1.5">@foreach ($featured as $i => $p)<button type="button" class="h-2 w-6 rounded-full bg-navy-100" data-dot aria-label="Show {{ $p->name }}"></button>@endforeach</div>
                                <div class="flex gap-1">
                                    <button type="button" class="btn-light btn-sm" data-prev aria-label="Previous"><x-icon name="chevron-left" class="h-4 w-4" /></button>
                                    <button type="button" class="btn-light btn-sm" data-next aria-label="Next"><x-icon name="chevron-right" class="h-4 w-4" /></button>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-3xl bg-page p-10 text-center lg:mt-24">
                        <p class="text-xl font-extrabold">New layouts are on their way</p>
                        <p class="mt-2 text-muted">Save what you are looking for and we will email you when a matching plot is launched.</p>
                        <a href="{{ route('market.requirement') }}" class="btn-primary btn-pill mt-5">Describe your plot</a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- How it works: a real sequence --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <h2 class="max-w-2xl text-3xl font-extrabold tracking-tight sm:text-4xl">From a plot you like to a registered sale deed</h2>
        <ol class="mt-10 grid gap-6 md:grid-cols-4">
            @foreach ([
                ['Pick a plot on the live map', 'Every plot shows its patta number, size, facing, boundaries and today\'s price — offers included.'],
                ['Book it online', 'Booking is free. The plot is held for you for the promoter\'s booking period while you pay the first instalment.'],
                ['Pay in instalments', 'Pay the promoter by bank transfer, UPI or cheque. Every payment gets a numbered receipt in your account.'],
                ['Register', 'The promoter prepares the registration pack and the sale deed is registered at your SRO.'],
            ] as $i => [$h, $t])
                <li class="reveal">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-navy text-lg font-extrabold text-white">{{ $i + 1 }}</span>
                    <h3 class="mt-4 text-lg font-bold">{{ $h }}</h3>
                    <p class="mt-1 text-muted">{{ $t }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Trust --}}
    <section class="bg-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Fewer surprises at the site visit</h2>
                <p class="mt-4 text-lg text-muted">Promoters on vector7 track every approval stage — land records, layout approval, gift deed, RERA — before a layout can be launched. What you see is what has been approved.</p>
            </div>
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ([['shield', 'Approvals tracked', 'Layouts go live only after their approval stages are complete.'], ['map', 'Live plot status', 'Booked and sold plots are marked the moment it happens.'], ['receipt', 'Receipts for every rupee', 'Numbered PDF receipts in your account, always.'], ['doc', 'Documents in one place', 'Registration pack, acknowledgement and receipts to download any time.']] as [$icon, $h, $t])
                    <li class="rounded-2xl bg-page p-5"><span class="text-teal-700"><x-icon :name="$icon" class="h-6 w-6" /></span><p class="mt-3 font-bold">{{ $h }}</p><p class="mt-1 text-sm text-muted">{{ $t }}</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($services->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-3xl font-extrabold tracking-tight">Help with the paperwork</h2>
                <a href="{{ route('market.services') }}" class="font-semibold">All services</a>
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
                <h2 class="text-3xl font-extrabold tracking-tight text-white">Run your layouts on vector7</h2>
                <p class="mt-3 text-navy-100">Approvals, estimates, plot launch, bookings, instalments and registration — one workspace for your whole team. Start on a free trial.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('signup') }}" class="btn btn-pill bg-gold px-7 py-3 text-navy hover:bg-amber-300 hover:text-navy">Create a workspace</a>
                <a href="{{ route('market.pricing') }}" class="btn btn-pill px-7 py-3 text-white ring-1 ring-white/30 hover:bg-white/10 hover:text-white">See plans</a>
            </div>
        </div>
    </section>
</x-layouts.public>
