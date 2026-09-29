<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="now()->format('l, j M Y').' · All layout projects'">
        <x-slot:actions>
            <div role="group" aria-label="Period" class="flex rounded-[10px] border border-line bg-white p-[3px]">
                @foreach (['month' => 'This month', 'quarter' => 'Quarter', 'year' => 'Year'] as $key => $label)
                    <a href="{{ route('app.dashboard', ['period' => $key]) }}" class="flex h-8 items-center rounded-lg px-3.5 text-[13px] font-semibold no-underline {{ $period === $key ? 'bg-teal text-white hover:text-white' : 'text-ink-2' }}">{{ $label }}</a>
                @endforeach
            </div>
        </x-slot:actions>
    </x-page-header>

    @if ($websiteHolds || $newRequests)
        <a href="{{ route('app.requests.index') }}" class="mb-4 flex items-center gap-3 rounded-2xl border border-[#A3BFE3] bg-[#E2ECF8] px-4 py-3 text-[#1D4F91] no-underline hover:text-[#1D4F91]">
            <x-icon name="bell" class="h-5 w-5" /><span class="flex-1 text-sm font-semibold">{{ $websiteHolds }} website {{ \Illuminate\Support\Str::plural('hold', $websiteHolds) }} awaiting advance · {{ $newRequests }} new purchase {{ \Illuminate\Support\Str::plural('request', $newRequests) }}</span><x-icon name="chevron" class="h-5 w-5" />
        </a>
    @endif

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <x-kpi label="Sales this period" :value="\App\Support\Money::short($d['sales_value'])" :note="$d['sales_count'].' plots sold'" tone="good" />
        <x-kpi label="Collections due" :value="\App\Support\Money::short($d['collections_due'])" :note="$d['overdue_count'].' instalments overdue'" :tone="$d['overdue_count'] ? 'bad' : 'muted'" />
        <x-kpi label="Plots sold" :value="$d['plots_sold'].' / '.$d['plots_total']">
            <div class="progress mt-1"><span style="width: {{ $d['plots_total'] ? round($d['plots_sold'] / $d['plots_total'] * 100) : 0 }}%"></span></div>
        </x-kpi>
        @if ($d['pool'])
            <x-kpi :label="'Share value · '.$d['pool']->layout->name" :value="'₹'.number_format((float) $d['pool']->current_share_value, 2)"
                :note="($d['pool']->growthPct() >= 0 ? '▲ ' : '▼ ').abs($d['pool']->growthPct()).'% over face value'" :tone="$d['pool']->growthPct() >= 0 ? 'good' : 'bad'" />
        @else
            <x-kpi label="Share value" value="—" note="Create a share pool to track value" />
        @endif
    </section>

    <section class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card-pad lg:col-span-2">
            <div class="mb-3 flex items-center justify-between"><h2 class="section-title">Layout projects</h2><a href="{{ route('app.layouts.index') }}" class="text-[13px] font-semibold">View all</a></div>
            @forelse ($d['layouts'] as $layout)
                @php
                    $mandatory = $layout->stages->where('is_mandatory', true);
                    $progress = $mandatory->count() ? round($mandatory->where('status', 'completed')->count() / $mandatory->count() * 100) : 0;
                @endphp
                <a href="{{ route('app.layouts.show', $layout) }}" class="grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-2 border-t border-line-soft py-3 text-ink no-underline first:border-t-0 hover:text-ink sm:grid-cols-[1.6fr_1fr_90px_110px]">
                    <span><span class="block font-semibold">{{ $layout->name }}</span><span class="text-xs text-ink-muted">{{ $layout->code }} · {{ $layout->district ?? $layout->location }}</span></span>
                    <span class="order-last col-span-2 flex items-center gap-2 sm:order-none sm:col-span-1"><span class="progress flex-1"><span style="width: {{ $progress }}%"></span></span><span class="num text-xs text-ink-muted">{{ $progress }}%</span></span>
                    <span class="num hidden text-sm sm:block">{{ $layout->sold_count }} / {{ $layout->plots_count }}</span>
                    <span class="justify-self-end"><x-status :status="$layout->status" /></span>
                </a>
            @empty
                <x-empty title="No layout projects yet" icon="map">Create your first layout to start tracking stages. <a href="{{ route('app.layouts.create') }}">New layout</a></x-empty>
            @endforelse
        </div>

        <div class="card-pad">
            <h2 class="section-title mb-3">Plot status</h2>
            @php $max = max(1, max($d['status_counts'] ?: [0])); @endphp
            <div class="flex flex-col gap-2.5">
                @foreach (\App\Enums\PlotStatus::cases() as $status)
                    <div class="grid grid-cols-[96px_1fr_32px] items-center gap-2.5 text-[13px]">
                        <span>{{ $status->label() }}</span>
                        <span class="h-2.5 rounded bg-[#F0EEE3]"><span class="tile-{{ $status->css() }} block h-2.5 rounded border-0 p-0" style="width: {{ round($d['status_counts'][$status->value] / $max * 100) }}%"></span></span>
                        <span class="num text-right font-semibold">{{ $d['status_counts'][$status->value] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="card-pad lg:col-span-2">
            <div class="mb-1 flex items-center justify-between"><h2 class="section-title">Due this week</h2><a href="{{ route('app.sales.index', ['status' => 'overdue']) }}" class="text-[13px] font-semibold">Overdue sales</a></div>
            @forelse ($d['due_this_week'] as $i)
                <a href="{{ route('app.sales.show', $i->sale) }}" class="flex items-center gap-3 border-t border-line-soft py-3 text-ink no-underline first:border-t-0 hover:text-ink">
                    <span class="flex-1"><span class="block font-semibold">{{ $i->sale->customer->name }}</span><span class="text-xs text-ink-muted">{{ $i->sale->plot->plot_no }} · instalment {{ $i->seq }} ({{ (float) $i->pct }}%)</span></span>
                    <span class="num font-semibold">@inr($i->balance())</span>
                    <span class="{{ $i->due_date->isPast() && ! $i->due_date->isToday() ? 'badge-od' : 'badge-bk' }}">{{ $i->due_date->isPast() && ! $i->due_date->isToday() ? 'Overdue '.$i->due_date->diffInDays(now()->startOfDay()).'d' : 'Due '.$i->due_date->format('j M') }}</span>
                </a>
            @empty
                <x-empty title="Nothing due this week" icon="check" />
            @endforelse
        </div>
        <div class="card-pad">
            <h2 class="section-title mb-3">Bookings expiring soon</h2>
            @forelse ($d['expiring_bookings'] as $b)
                <a href="{{ route('app.plots.show', $b->plot) }}" class="flex items-center gap-3 border-t border-line-soft py-2.5 text-ink no-underline first:border-t-0 hover:text-ink">
                    <span class="flex h-11 w-11 flex-col items-center justify-center rounded-xl bg-[#FBEFD5] text-[#7A4F00]"><span class="num text-base font-extrabold leading-none">{{ $b->daysLeft() }}</span><span class="text-[10px] font-semibold">days</span></span>
                    <span class="text-sm"><span class="block font-semibold">{{ $b->plot->plot_no }} · {{ $b->customer->name }}</span><span class="text-xs text-ink-muted">expires {{ $b->expires_at->format('j M') }}</span></span>
                </a>
            @empty
                <p class="text-sm text-ink-muted">No bookings expire in the next 5 days.</p>
            @endforelse
            <h2 class="section-title mb-2 mt-5">Plan usage</h2>
            @foreach (['users' => 'Users', 'layouts' => 'Layout projects', 'plots' => 'Plots'] as $key => $label)
                <div class="mb-2 text-[13px]"><div class="flex justify-between"><span>{{ $label }}</span><span class="num text-ink-muted">{{ $usage[$key][0] }} / {{ $usage[$key][1] }}</span></div>
                <div class="progress mt-1"><span style="width: {{ min(100, $usage[$key][1] ? round($usage[$key][0] / $usage[$key][1] * 100) : 0) }}%"></span></div></div>
            @endforeach
        </div>
    </section>
</x-layouts.app>
