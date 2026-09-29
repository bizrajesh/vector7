@php
    use Illuminate\Support\Str;
    // Public view never reveals who holds a plot; statuses are simplified.
    $public = fn ($s) => match ($s->value) { 'available' => ['av', 'Available'], 'booked', 'reserved' => ['bk', 'On hold'], default => ['sold', 'Sold'] };
    $code = Str::lower($layout->code);
@endphp
<x-layouts.public :seo="$seo">
    <section class="bg-navy text-white">
        <div class="mx-auto max-w-6xl px-4 py-8">
            <nav aria-label="Breadcrumb" class="text-sm text-navy-200"><a href="{{ route('home') }}" class="text-navy-200 hover:text-white">Home</a> / <a href="{{ route('projects.index') }}" class="text-navy-200 hover:text-white">Projects</a> / <span>{{ $layout->name }}</span></nav>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="anim-up text-3xl font-extrabold md:text-4xl">{{ $layout->name }}</h1>
                    <p class="anim-up mt-1 text-navy-200" style="--d: 80ms">{{ collect([$layout->location, $layout->village, $layout->taluk, $layout->district])->filter()->implode(', ') }} · by {{ $tenant->name }}</p>
                </div>
                <div class="anim-up flex gap-3" style="--d: 160ms">
                    <div class="rounded-xl bg-white/10 px-4 py-2 text-center"><p class="num text-2xl font-extrabold text-sage" data-count="{{ $available }}">{{ $available }}</p><p class="text-xs text-navy-200">available</p></div>
                    <div class="rounded-xl bg-white/10 px-4 py-2 text-center"><p class="num text-2xl font-extrabold">{{ $plots->count() }}</p><p class="text-xs text-navy-200">plots</p></div>
                    @if ($layout->default_rate_sqft)<div class="rounded-xl bg-white/10 px-4 py-2 text-center"><p class="num text-2xl font-extrabold">₹{{ number_format((float) $layout->default_rate_sqft) }}</p><p class="text-xs text-navy-200">per sqft</p></div>@endif
                </div>
            </div>
            @if ($layout->public_summary)<p class="anim-up mt-4 max-w-3xl text-navy-200" style="--d: 240ms">{{ $layout->public_summary }}</p>@endif
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-8">
        <x-flash />
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-bold">Plot map <span class="ml-2 inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted"><span class="live-dot"></span>live</span></h2>
            <div class="flex gap-4 text-xs font-medium text-ink-2">
                <span class="flex items-center gap-1.5"><span class="tile-av h-3 w-3 rounded-[3px] border-0 p-0"></span>Available</span>
                <span class="flex items-center gap-1.5"><span class="tile-bk h-3 w-3 rounded-[3px] border-0 p-0"></span>On hold</span>
                <span class="flex items-center gap-1.5"><span class="tile-sold h-3 w-3 rounded-[3px] border-0 p-0"></span>Sold</span>
            </div>
        </div>
        <p class="mb-4 text-sm text-ink-muted">Tap a green plot to see details and hold it online.</p>

        <div class="grid grid-cols-5 gap-2 sm:grid-cols-8 lg:grid-cols-12">
            @foreach ($plots as $i => $plot)
                @php [$css, $label] = $public($plot->status); $open = $css === 'av'; @endphp
                <button type="button" class="tile-{{ $css }} anim-pop {{ $open ? '' : 'cursor-default opacity-90' }}" style="--d: {{ min($i, 60) * 15 }}ms"
                    data-plot data-no="Plot {{ $plot->plot_no }}" data-survey="{{ $plot->dimensions ? $plot->dimensions.' ft' : 'Dimensions on request' }}"
                    data-size="{{ number_format((float) $plot->size_sqft) }} sqft" data-facing="{{ $plot->facing ? ucwords(str_replace('_', ' ', $plot->facing)) : '—' }}"
                    data-rate="₹{{ number_format((float) $plot->rate_sqft) }} / sqft" data-cost="{{ \App\Support\Money::inr($plot->cost) }}"
                    data-north="{{ $plot->boundary_north }}" data-south="{{ $plot->boundary_south }}" data-east="{{ $plot->boundary_east }}" data-west="{{ $plot->boundary_west }}"
                    data-status="{{ $label }}" data-css="{{ $css }}" data-url="#callback"
                    @if ($open) data-book-url="{{ route('booking.create', [$tenant->slug, $code, $plot->plot_no]) }}" data-callback="1" @endif
                    aria-label="Plot {{ $plot->plot_no }}, {{ $label }}, {{ number_format((float) $plot->size_sqft) }} square feet">
                    <strong>{{ $plot->plot_no }}</strong><span>{{ number_format((float) $plot->size_sqft) }}</span>
                </button>
            @endforeach
        </div>

        <section id="callback" class="reveal mt-12 grid gap-6 rounded-2xl bg-white p-5 shadow-card md:grid-cols-2 md:p-8">
            <div>
                <h2 class="text-2xl font-bold">Want to buy? Talk to the sales team</h2>
                <p class="mt-2 text-ink-2">Purchases are completed only with {{ $tenant->name }}'s sales team — they will explain the price, the 30/60/10 payment plan and registration. No payment is taken on this website.</p>
                @if ($tenant->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $tenant->phone) }}" class="btn-outline mt-5"><x-icon name="phone" class="h-5 w-5" />Call {{ $tenant->phone }}</a>@endif
            </div>
            <form method="POST" action="{{ route('projects.callback', [$tenant->slug, $code]) }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <div class="hidden" aria-hidden="true"><label for="cb-website">Website</label><input id="cb-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>
                <x-field name="name" label="Your name" required autocomplete="name" />
                <x-field name="phone" type="tel" label="Mobile" required inputmode="tel" autocomplete="tel" />
                <x-field name="email" type="email" label="Email (optional)" autocomplete="email" />
                <x-field name="plot_no" label="Plot no (optional)" />
                <x-field name="message" label="Message (optional)" class="sm:col-span-2" />
                <div class="sm:col-span-2"><button class="btn-primary w-full sm:w-auto">Request a call back</button></div>
            </form>
        </section>
    </div>

    {{-- Plot details sheet (bottom sheet on phones, centred on desktop) --}}
    <dialog id="plot-sheet" data-sheet-always class="m-0 mt-auto w-full max-w-none rounded-t-3xl bg-white p-0 shadow-sheet backdrop:bg-ink/40 sm:m-auto sm:max-w-md sm:rounded-3xl">
        <div class="flex flex-col gap-4 px-5 pb-7 pt-2.5">
            <button type="button" data-close-sheet class="mx-auto h-1.5 w-10 rounded-full bg-[#D6D2BD]" aria-label="Close"></button>
            <div class="flex items-start justify-between">
                <div><p class="text-[22px] font-bold" data-field="no"></p><p class="text-[13px] text-ink-muted" data-field="survey"></p></div>
                <span data-badge></span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                <div class="rounded-xl bg-ground px-3.5 py-3"><p class="text-xs text-ink-muted">Size</p><p class="num font-bold" data-field="size"></p></div>
                <div class="rounded-xl bg-ground px-3.5 py-3"><p class="text-xs text-ink-muted">Facing</p><p class="font-bold" data-field="facing"></p></div>
                <div class="rounded-xl bg-ground px-3.5 py-3"><p class="text-xs text-ink-muted">Rate</p><p class="num font-bold" data-field="rate"></p></div>
                <div class="rounded-xl bg-teal-50 px-3.5 py-3"><p class="text-xs text-teal">Plot price</p><p class="num font-extrabold text-navy" data-field="cost"></p></div>
            </div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[13px] text-ink-2">
                <span><strong class="text-ink">N</strong> · <span data-field="north"></span></span><span><strong class="text-ink">S</strong> · <span data-field="south"></span></span>
                <span><strong class="text-ink">E</strong> · <span data-field="east"></span></span><span><strong class="text-ink">W</strong> · <span data-field="west"></span></span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <a data-book href="#" class="btn-primary h-[52px]">Hold online</a>
                <a data-buy href="#callback" class="btn-outline h-[52px]">Buy via sales</a>
            </div>
            <a data-view href="#callback" class="hidden">Contact</a>
            <p class="text-center text-xs text-ink-muted">Holding is free. The sales team collects the advance and completes the purchase.</p>
        </div>
    </dialog>
</x-layouts.public>
