@php
    $funnel = collect(['available', 'booked', 'ongoing_sale', 'ror', 'ongoing_reg', 'sold'])->map(fn ($s) => $d['status_counts'][$s] ?? 0);
    $funnelChart = ['labels' => ['Available', 'Booked', 'Ongoing-Sale', 'ROR', 'Ongoing-Reg', 'Sold'], 'datasets' => [['label' => 'Plots', 'data' => $funnel->values()]]];
    $cashChart = ['labels' => collect($d['cashflow'])->pluck('label'), 'datasets' => [
        ['label' => 'Money in', 'data' => collect($d['cashflow'])->pluck('in'), 'backgroundColor' => '#227C70'],
        ['label' => 'Money out', 'data' => collect($d['cashflow'])->pluck('out'), 'backgroundColor' => '#1C315E'],
    ]];
    $ageChart = ['labels' => $ageing->keys()->values(), 'datasets' => [['label' => 'Overdue ₹', 'data' => $ageing->values()->map(fn ($v) => round($v))]]];
@endphp
<x-layouts.app title="Business Analytics">
    <x-page-header title="Business Analytics" subtitle="Decision support: sales funnel, collections, cash flow and share growth">
        <x-slot:actions>
            <form method="GET" class="flex gap-2">
                <label for="a-layout" class="sr-only">Project</label>
                <select id="a-layout" name="layout_id" class="input min-h-[40px] w-48 py-1 text-sm">
                    <option value="">All projects</option>
                    @foreach ($layouts as $l)<option value="{{ $l->id }}" @selected((int) request('layout_id') === $l->id)>{{ $l->name }}</option>@endforeach
                </select>
                <button class="btn-ghost btn-sm">Apply</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Sales (year)" :value="\App\Support\Money::short($d['sales_value'])" :note="$d['sales_count'].' plots'" tone="good" />
        <x-kpi label="Collections due" :value="\App\Support\Money::short($d['collections_due'])" :note="$d['overdue_count'].' overdue'" :tone="$d['overdue_count'] ? 'bad' : 'muted'" />
        <x-kpi label="Sell-through" :value="($d['plots_total'] ? round($d['plots_sold'] / $d['plots_total'] * 100) : 0).'%'" :note="$d['plots_sold'].' of '.$d['plots_total'].' plots sold'" />
        <x-kpi label="Unsold inventory" :value="($d['status_counts']['available'] ?? 0).' plots'" :note="($d['status_counts']['booked'] ?? 0).' booked'" />
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card-pad"><h2 class="section-title mb-3">Sales funnel</h2><div class="h-64"><canvas data-chart="bar" data-source="funnel" role="img" aria-label="Plots by status"></canvas></div><script type="application/json" id="funnel">@json($funnelChart)</script></section>
        <section class="card-pad"><h2 class="section-title mb-3">Cash flow · last 6 months</h2><div class="h-64"><canvas data-chart="bar" data-source="cash" role="img" aria-label="Money in and out by month"></canvas></div><script type="application/json" id="cash">@json($cashChart)</script></section>
        <section class="card-pad"><h2 class="section-title mb-3">Overdue ageing</h2>
            @if ($ageing->isEmpty())<p class="text-sm text-ink-muted">No overdue instalments.</p>@else<div class="h-56"><canvas data-chart="doughnut" data-source="ageing" role="img" aria-label="Overdue amounts by age"></canvas></div><script type="application/json" id="ageing">@json($ageChart)</script>@endif
        </section>
        <section class="card-pad"><h2 class="section-title mb-3">Sales team</h2>
            @forelse ($salesTeam as $row)
                <div class="flex justify-between border-t border-line-soft py-2.5 text-sm first:border-t-0"><span class="font-semibold">{{ $row['name'] }}</span><span class="num">{{ $row['sales'] }} sales · @inrShort($row['value'])</span></div>
            @empty
                <p class="text-sm text-ink-muted">No Sales users yet.</p>
            @endforelse
        </section>
        @unless ($salesOnly)
            <section class="card-pad lg:col-span-2"><h2 class="section-title mb-3">Share value by project</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($pools as $pool)
                        <a href="{{ route('app.shares.show', $pool) }}" class="rounded-xl bg-ground p-3 text-ink no-underline hover:text-ink"><span class="block text-sm font-semibold">{{ $pool->layout->name }}</span><span class="num text-xl font-bold">₹{{ number_format((float) $pool->current_share_value, 2) }}</span> <span class="text-xs font-semibold {{ $pool->growthPct() >= 0 ? 'text-[#1F6B45]' : 'text-[#A12622]' }}">{{ $pool->growthPct() }}%</span><span class="block text-xs text-ink-muted">{{ $pool->events->count() }} sales recognised</span></a>
                    @empty
                        <p class="text-sm text-ink-muted">No share pools yet.</p>
                    @endforelse
                </div>
            </section>
        @endunless
    </div>
</x-layouts.app>
