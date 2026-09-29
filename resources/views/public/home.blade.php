@php
    $plotsAvailable = $featured->sum('plots_available');
    $demoTiles = [
        ['sold', 0], ['sold', 0], ['av', 0], ['bk', 0], ['av', 0], ['cycle', 0], ['av', 0], ['bk', 0], ['cycle', 3000], ['av', 0],
        ['av', 0], ['cycle', 6000], ['sold', 0], ['av', 0], ['rs', 0], ['av', 0], ['cycle', 1500], ['sold', 0], ['av', 0], ['av', 0],
    ];
@endphp
<x-layouts.public :seo="$seo">
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-navy text-white">
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal/30 blur-3xl anim-in" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-20 h-80 w-80 rounded-full bg-sage/20 blur-3xl anim-in" style="--d: 300ms" aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 md:grid-cols-2 md:py-20">
            <div>
                <p class="anim-up flex items-center gap-2 text-sm font-semibold tracking-wide text-cream"><span class="live-dot"></span> LIVE PLOT AVAILABILITY</p>
                <h1 class="anim-up mt-3 text-4xl font-extrabold leading-tight md:text-5xl" style="--d: 100ms">Find your plot. See what's available — right now.</h1>
                <p class="anim-up mt-4 text-lg text-navy-200" style="--d: 200ms">Browse approved layout projects, check size, facing and price, and hold a plot online in two minutes. Our sales team completes the purchase with you.</p>
                <div class="anim-up mt-7 flex flex-wrap gap-3" style="--d: 300ms">
                    <a href="{{ route('projects.index') }}" class="btn btn-shine bg-sage text-navy hover:bg-cream hover:text-navy"><x-icon name="grid" class="h-5 w-5" />Browse projects</a>
                    <a href="{{ route('track') }}" class="btn border border-white/30 text-white hover:bg-white/10 hover:text-white"><x-icon name="search" class="h-5 w-5" />Track my booking</a>
                </div>
                <p class="anim-up mt-6 text-sm text-navy-200" style="--d: 400ms">Are you a developer? <a href="{{ route('register') }}" class="font-semibold text-cream hover:text-white">Start a free trial</a> and publish your layouts.</p>
            </div>

            <div class="relative">
                <div class="anim-up rounded-2xl bg-white/[0.06] p-5 ring-1 ring-white/10" style="--d: 250ms">
                    <div class="flex items-center justify-between text-sm"><span class="font-semibold">Plot map</span><span class="flex items-center gap-2 text-navy-200"><span class="live-dot"></span>updates live</span></div>
                    <div class="mt-4 grid grid-cols-5 gap-2" aria-hidden="true">
                        @foreach ($demoTiles as $i => [$css, $cycleDelay])
                            @if ($css === 'cycle')
                                <span class="tile tile-live border-[#9CCFB2] bg-[#E3F2EA] text-[#1F6B45]" style="--d: {{ 300 + $i * 40 }}ms; --c: {{ $cycleDelay }}ms"><strong>{{ $i + 1 }}</strong><span>1,200</span></span>
                            @else
                                <span class="tile-{{ $css }} anim-pop" style="--d: {{ 300 + $i * 40 }}ms"><strong>{{ $i + 1 }}</strong><span>1,200</span></span>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3 text-xs text-navy-200">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#9CCFB2]"></span>Available</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#E6C27A]"></span>On hold</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-white"></span>Sold</span>
                    </div>
                </div>
                <div class="anim-float absolute -bottom-8 -left-4 hidden w-60 rounded-2xl bg-white p-4 text-ink shadow-card sm:block" aria-hidden="true">
                    <p class="text-xs text-ink-muted">Plot 24 · 1,200 sqft · East</p>
                    <p class="num mt-1 text-xl font-extrabold text-navy">₹17,40,000</p>
                    <svg viewBox="0 0 200 40" class="mt-2 h-8 w-full"><path class="draw-line" d="M0 35 L30 30 L60 32 L90 22 L120 24 L150 12 L200 6" fill="none" stroke="#227C70" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <p class="text-xs font-semibold text-teal">Hold online · 48 hours</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Live numbers --}}
    <section class="mx-auto -mt-6 max-w-6xl px-4">
        <div class="relative grid grid-cols-3 gap-3 rounded-2xl bg-white p-4 shadow-card sm:p-6">
            <div class="text-center"><p class="num text-2xl font-extrabold text-navy sm:text-3xl" data-count="{{ $featured->count() }}">{{ $featured->count() }}</p><p class="text-xs text-ink-muted sm:text-sm">projects open now</p></div>
            <div class="text-center"><p class="num text-2xl font-extrabold text-teal sm:text-3xl" data-count="{{ $plotsAvailable }}">{{ number_format($plotsAvailable) }}</p><p class="text-xs text-ink-muted sm:text-sm">plots available</p></div>
            <div class="text-center"><p class="num text-2xl font-extrabold text-navy sm:text-3xl">48 h</p><p class="text-xs text-ink-muted sm:text-sm">online hold</p></div>
        </div>
    </section>

    {{-- Featured projects --}}
    <section class="mx-auto max-w-6xl px-4 py-14">
        <div class="reveal flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-3xl font-bold">Projects open for booking</h2>
                <p class="mt-2 text-ink-2">Launched, approved layouts with live plot availability.</p>
            </div>
            <a href="{{ route('projects.index') }}" class="btn-outline">View all projects</a>
        </div>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($featured as $i => $layout)
                <a href="{{ route('projects.show', [$layout->tenant->slug, \Illuminate\Support\Str::lower($layout->code)]) }}" class="reveal lift card group flex flex-col overflow-hidden text-ink no-underline hover:text-ink">
                    <div class="relative h-28 bg-navy">
                        <div class="absolute inset-0 grid grid-cols-8 gap-1 p-3 opacity-80" aria-hidden="true">
                            @for ($t = 0; $t < 24; $t++)
                                <span class="rounded-[4px] {{ $t % 7 === 0 ? 'bg-white/80' : ($t % 5 === 0 ? 'bg-[#E6C27A]' : 'bg-sage/70') }}"></span>
                            @endfor
                        </div>
                        <span class="absolute bottom-3 left-3 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-teal">{{ $layout->plots_available }} available</span>
                    </div>
                    <div class="flex flex-1 flex-col gap-1.5 p-4">
                        <h3 class="text-lg font-bold group-hover:text-teal">{{ $layout->name }}</h3>
                        <p class="text-sm text-ink-muted">{{ collect([$layout->location, $layout->district])->filter()->implode(' · ') }}</p>
                        <p class="text-sm text-ink-2">{{ \Illuminate\Support\Str::limit($layout->public_summary, 110) }}</p>
                        <div class="mt-auto flex items-end justify-between pt-3">
                            <span class="text-xs text-ink-muted">by {{ $layout->tenant->name }}</span>
                            @if ($layout->price_from)<span class="num text-sm font-bold">from @inrShort($layout->price_from)</span>@endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="card-pad reveal sm:col-span-2 lg:col-span-3"><x-empty title="New projects are launching soon" icon="map">Check back shortly, or ask a developer you know to publish their layout on Vector7.</x-empty></div>
            @endforelse
        </div>
    </section>

    {{-- How buying works --}}
    <section class="bg-white">
        <div class="mx-auto max-w-6xl px-4 py-14">
            <h2 class="reveal text-3xl font-bold">How buying a plot works</h2>
            <p class="reveal mt-2 max-w-2xl text-ink-2">Hold online, then buy with a person you can talk to. Payments are never taken on this website.</p>
            <ol class="mt-8 grid gap-6 md:grid-cols-4">
                @foreach ([
                    ['grid', 'Choose a plot', 'Open a project, tap a green plot and see size, facing, boundaries and price.'],
                    ['clock', 'Hold it online', 'Enter your name, phone and email and confirm with a 6-digit code. The plot is held for you for 48 hours.'],
                    ['phone', 'Sales team calls you', 'Pay the booking advance to the sales team and your booking is confirmed for 15 days.'],
                    ['check', 'Buy & register', 'Complete the purchase with the sales team in 30/60/10 instalments, then register. Track everything online.'],
                ] as $n => [$icon, $title, $text])
                    <li class="reveal relative">
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal text-white shadow-card"><x-icon :name="$icon" /></span>
                            @if (! $loop->last)<span class="step-line hidden h-0.5 flex-1 bg-gradient-to-r from-teal to-sage md:block" style="--d: {{ 300 + $n * 250 }}ms"></span>@endif
                        </div>
                        <p class="mt-4 text-xs font-bold text-teal">STEP {{ $n + 1 }}</p>
                        <h3 class="mt-1 font-bold">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm text-ink-2">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
            <div class="reveal mt-10 flex flex-wrap gap-3">
                <a href="{{ route('projects.index') }}" class="btn-primary">Find a plot</a>
                <a href="{{ route('track') }}" class="btn-outline">Already booked? Track it</a>
            </div>
        </div>
    </section>

    {{-- For developers --}}
    <section class="mx-auto max-w-6xl px-4 py-14">
        <div class="reveal grid items-center gap-10 md:grid-cols-2">
            <div>
                <p class="text-sm font-bold tracking-wide text-teal">FOR LAYOUT DEVELOPERS</p>
                <h2 class="mt-2 text-3xl font-bold">Run your layouts from land to registration</h2>
                <p class="mt-3 text-ink-2">Track approval stages and budgets, publish plots to this site, collect instalments, finish registrations and show shareholders how every sale grows their share value — all in one secure workspace.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="btn-primary">Start free trial</a>
                    <a href="{{ route('features') }}" class="btn-ghost">See features</a>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach ([['ops', 'Approval stages', 'Budgets, dependencies, alerts'], ['grid', 'Plot catalogue', 'Bookings that expire on time'], ['wallet', 'Payments', '30/60/10 with receipts'], ['pie', 'Shareholders', 'Value moves only on sales']] as [$icon, $title, $text])
                    <div class="lift card-pad reveal">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal"><x-icon :name="$icon" /></span>
                        <p class="mt-3 font-bold">{{ $title }}</p>
                        <p class="text-sm text-ink-muted">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Trust --}}
    <section class="bg-navy text-white">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 md:grid-cols-3">
            @foreach ([['lock', 'Your details are protected', 'Aadhaar and PAN are encrypted and never shown in full.'], ['check', 'No online payments', 'You pay only the sales team, and every payment gets a receipt.'], ['search', 'Track anytime', 'Sign in with a one-time code to see your plot, dues and receipts.']] as [$icon, $title, $text])
                <div class="reveal flex gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-sage"><x-icon :name="$icon" /></span>
                    <div><p class="font-bold">{{ $title }}</p><p class="mt-1 text-sm text-navy-200">{{ $text }}</p></div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.public>
