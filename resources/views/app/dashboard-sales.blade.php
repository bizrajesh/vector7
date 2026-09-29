@php
    $collectToday = $dueToday->sum(fn ($i) => $i->balance());
    $overdue = $dueToday->filter(fn ($i) => $i->due_date->lt(now()->startOfDay()))->count();
    $myCount = (clone $mySales)->count();
    $myValue = (clone $mySales)->sum('sale_value');
@endphp
<x-layouts.app title="Home">
    <div class="-mx-4 -mt-5 bg-navy px-5 pb-16 pt-5 text-white sm:-mx-8 sm:-mt-6 sm:px-8">
        <p class="text-sm text-navy-200">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},</p>
        <p class="text-2xl font-bold">{{ auth()->user()->name }}</p>
    </div>
    <div class="-mt-12 grid grid-cols-2 gap-3">
        <div class="card-pad shadow-card"><span class="kpi-label">Collect today</span><p class="kpi-value text-[22px]">@inrShort($collectToday)</p><p class="text-xs font-semibold {{ $overdue ? 'text-[#A12622]' : 'text-ink-muted' }}">{{ $overdue }} overdue</p></div>
        <div class="card-pad shadow-card"><span class="kpi-label">My sales · {{ now()->format('M') }}</span><p class="kpi-value text-[22px]">{{ $myCount }} {{ \Illuminate\Support\Str::plural('plot', $myCount) }}</p><p class="text-xs font-semibold text-[#1F6B45]">@inrShort($myValue) value</p></div>
    </div>

    <div class="card mt-4 grid grid-cols-4 gap-1 px-3 py-4">
        @php $first = $layouts->first(); @endphp
        @foreach ([
            ['Book plot', 'tag', 'bg-teal-50 text-teal', $first ? route('app.plots.index', [$first, 'status' => 'available']) : route('app.layouts.index')],
            ['Payment', 'wallet', 'bg-[#FBEFD5] text-[#8A5300]', route('app.sales.index', ['status' => 'ongoing'])],
            ['Customer', 'user-plus', 'bg-[#E2ECF8] text-[#1D4F91]', route('app.customers.index')],
            ['Plot map', 'grid', 'bg-[#EEE7F8] text-[#553590]', $first ? route('app.plots.index', $first) : route('app.layouts.index')],
        ] as [$label, $icon, $tone, $url])
            <a href="{{ $url }}" class="flex flex-col items-center gap-2 text-xs font-semibold text-ink no-underline hover:text-ink">
                <span class="flex h-12 w-12 items-center justify-center rounded-[14px] {{ $tone }}"><x-icon :name="$icon" class="h-[22px] w-[22px]" /></span>{{ $label }}
            </a>
        @endforeach
    </div>

    <section class="mt-5">
        <div class="mb-2 flex items-center justify-between"><h2 class="section-title">Due today</h2><a href="{{ route('app.sales.index', ['status' => 'overdue']) }}" class="text-[13px] font-semibold">See all</a></div>
        <div class="card">
            @forelse ($dueToday as $i)
                <a href="{{ route('app.sales.show', $i->sale) }}" class="flex items-center gap-3 border-t border-line-soft px-4 py-3.5 text-ink no-underline first:border-t-0 hover:text-ink">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#F0EEE3] text-[13px] font-bold">{{ mb_strtoupper(mb_substr($i->sale->customer->name, 0, 2)) }}</span>
                    <span class="flex-1"><span class="block text-[14.5px] font-semibold">{{ $i->sale->customer->name }}</span><span class="text-[12.5px] text-ink-muted">{{ $i->sale->plot->plot_no }} · {{ (float) $i->pct }}% instalment</span></span>
                    <span class="flex flex-col items-end gap-1"><span class="num text-[14.5px] font-bold">@inr($i->balance())</span>
                        <span class="{{ $i->due_date->lt(now()->startOfDay()) ? 'badge-od' : 'badge-bk' }}">{{ $i->due_date->lt(now()->startOfDay()) ? 'Overdue '.$i->due_date->diffInDays(now()->startOfDay()).'d' : 'Today' }}</span></span>
                </a>
            @empty
                <x-empty title="No collections due today" icon="check" />
            @endforelse
        </div>
    </section>

    @if ($websiteHolds->isNotEmpty() || $newRequests)
        <section class="mt-5">
            <div class="mb-2 flex items-center justify-between"><h2 class="section-title">From the website</h2><a href="{{ route('app.requests.index') }}" class="text-[13px] font-semibold">{{ $newRequests }} new requests</a></div>
            @foreach ($websiteHolds as $b)
                <a href="{{ route('app.plots.show', $b->plot) }}" class="card mb-2 flex items-center gap-3 px-4 py-3.5 text-ink no-underline hover:text-ink">
                    <span class="flex h-[52px] w-[52px] flex-col items-center justify-center rounded-xl bg-[#E2ECF8] text-[#1D4F91]"><span class="num text-lg font-extrabold leading-none">{{ $b->hoursLeft() }}</span><span class="text-[10.5px] font-semibold">hours</span></span>
                    <span class="flex-1"><span class="block text-[14.5px] font-semibold">{{ $b->plot->plot_no }} · {{ $b->customer->name }}</span><span class="text-[12.5px] text-ink-muted">{{ $b->customer->phone }} · online hold · collect advance</span></span>
                    <x-icon name="chevron" class="h-5 w-5 text-ink-muted" />
                </a>
            @endforeach
        </section>
    @endif

    <section class="mt-5">
        <h2 class="section-title mb-2">Bookings expiring</h2>
        @forelse ($expiring as $b)
            <div class="card mb-2 flex items-center gap-3 px-4 py-3.5">
                <span class="flex h-[52px] w-[52px] flex-col items-center justify-center rounded-xl bg-[#FBEFD5] text-[#7A4F00]"><span class="num text-lg font-extrabold leading-none">{{ $b->daysLeft() }}</span><span class="text-[10.5px] font-semibold">days</span></span>
                <span class="flex-1"><span class="block text-[14.5px] font-semibold">{{ $b->plot->plot_no }} · {{ $b->customer->name }}</span><span class="text-[12.5px] text-ink-muted">Booked {{ $b->booked_at->format('j M') }} · expires {{ $b->expires_at->format('j M') }}</span></span>
                <a href="{{ route('app.sales.create', $b->plot) }}" class="btn-outline btn-sm">Convert</a>
            </div>
        @empty
            <p class="text-sm text-ink-muted">No bookings expire in the next 5 days.</p>
        @endforelse
    </section>
</x-layouts.app>
