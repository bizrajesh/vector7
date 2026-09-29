@php
    $user = auth()->user();
    $launched = $layout->status->value === 'launched';
@endphp
<x-layouts.app :title="'Plots · '.$layout->name">
    <x-page-header :title="$layout->name" :subtitle="$layout->code.' · '.array_sum($counts).' plots'.($layout->default_rate_sqft ? ' · ₹'.number_format((float) $layout->default_rate_sqft).' / ft²' : '')" :back="route('app.layouts.show', $layout)">
        <x-slot:actions>
            @can('plots.manage')
                <a href="{{ route('app.plots.import', $layout) }}" class="btn-ghost btn-sm"><x-icon name="upload" class="h-4 w-4" />Import plots</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($launched)
        <div class="mb-4 rounded-xl border border-[#F1D9A6] bg-[#FDF6E7] px-4 py-3 text-sm text-[#5E3D00]">This project is not launched yet — plots cannot be booked or sold until you launch it.</div>
    @endunless

    <form method="GET" class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Filter plots">
        <a href="{{ route('app.plots.index', $layout) }}" class="pill {{ ! request('status') ? 'active' : '' }}">All {{ array_sum($counts) }}</a>
        @foreach (\App\Enums\PlotStatus::cases() as $s)
            @if ($counts[$s->value] ?? 0)
                <a href="{{ route('app.plots.index', [$layout, 'status' => $s->value]) }}" class="pill {{ request('status') === $s->value ? 'active' : '' }}">{{ $s->label() }} {{ $counts[$s->value] }}</a>
            @endif
        @endforeach
        <label class="sr-only" for="plot-q">Plot number</label>
        <input id="plot-q" name="q" value="{{ request('q') }}" placeholder="Plot no" class="input h-9 min-h-0 w-28 rounded-full py-0 text-[13px]">
    </form>

    <div class="mb-3 flex flex-wrap gap-x-4 gap-y-2 text-[11.5px] font-medium text-ink-2">
        @foreach (\App\Enums\PlotStatus::cases() as $s)
            <span class="flex items-center gap-1.5"><span class="tile-{{ $s->css() }} h-2.5 w-2.5 rounded-[3px] border-0 p-0"></span>{{ $s->label() }}</span>
        @endforeach
    </div>

    @if ($plots->isEmpty())
        <div class="card"><x-empty title="No plots yet" icon="grid">Import plots from a CSV file to build the catalogue.</x-empty></div>
    @else
        <div class="grid grid-cols-5 gap-2 sm:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12">
            @foreach ($plots as $plot)
                @php
                    $canBook = $launched && $plot->status->value === 'available' && $user->can('bookings.create');
                    $canSell = $launched && in_array($plot->status->value, ['available', 'booked']) && $user->can('sales.create');
                @endphp
                <a href="{{ route('app.plots.show', $plot) }}" class="tile-{{ $plot->status->css() }}"
                   data-plot data-no="Plot {{ $plot->plot_no }}" data-survey="Survey No. {{ $plot->survey_no ?? '—' }}{{ $plot->dimensions ? ' · '.$plot->dimensions.' ft' : '' }}"
                   data-size="{{ number_format((float) $plot->size_sqft) }} sqft" data-facing="{{ $plot->facing ? ucwords(str_replace('_', ' ', $plot->facing)) : '—' }}"
                   data-rate="₹{{ number_format((float) $plot->rate_sqft) }} / sqft" data-cost="{{ \App\Support\Money::inr($plot->cost) }}"
                   data-north="{{ $plot->boundary_north }}" data-south="{{ $plot->boundary_south }}" data-east="{{ $plot->boundary_east }}" data-west="{{ $plot->boundary_west }}"
                   data-status="{{ $plot->status->label() }}" data-css="{{ $plot->status->css() }}" data-url="{{ route('app.plots.show', $plot) }}"
                   @if ($canBook) data-book-url="{{ route('app.bookings.create', $plot) }}" @endif
                   @if ($canSell) data-sell-url="{{ route('app.sales.create', $plot) }}" @endif
                   aria-label="Plot {{ $plot->plot_no }}, {{ $plot->status->label() }}, {{ number_format((float) $plot->size_sqft) }} square feet">
                    <strong>{{ $plot->plot_no }}</strong><span>{{ number_format((float) $plot->size_sqft) }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Phone bottom sheet (filled by /js/app.js with textContent) --}}
    <dialog id="plot-sheet" class="m-0 mt-auto w-full max-w-none rounded-t-3xl bg-white p-0 shadow-sheet backdrop:bg-ink/40">
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
                <div class="rounded-xl bg-teal-50 px-3.5 py-3"><p class="text-xs text-teal">Plot cost</p><p class="num font-extrabold text-navy" data-field="cost"></p></div>
            </div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[13px] text-ink-2">
                <span><strong class="text-ink">N</strong> · <span data-field="north"></span></span><span><strong class="text-ink">S</strong> · <span data-field="south"></span></span>
                <span><strong class="text-ink">E</strong> · <span data-field="east"></span></span><span><strong class="text-ink">W</strong> · <span data-field="west"></span></span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <a data-book href="#" class="btn-outline h-[52px]">Book · 15 days</a>
                <a data-buy href="#" class="btn-primary h-[52px]">Buy now</a>
            </div>
            <a data-view href="#" class="text-center text-sm font-semibold">Open plot details</a>
        </div>
    </dialog>
</x-layouts.app>
