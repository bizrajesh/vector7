@php
    $revenueChart = ['labels' => collect($revenue)->pluck('label'), 'datasets' => [['label' => 'Collections (₹)', 'data' => collect($revenue)->pluck('value'), 'borderColor' => '#227C70']]];
    $totalTenants = array_sum($statusCounts);
    $maxPlan = max(1, $planMix->max('tenants_count'));
@endphp
<x-layouts.platform title="Platform overview">
    <x-page-header title="Platform overview" :subtitle="now()->format('j M Y')">
        <x-slot:actions>
            <a href="{{ route('platform.plans.index') }}" class="btn-ghost btn-sm">Manage plans</a>
            <a href="{{ route('platform.tenants.index') }}" class="btn-navy btn-sm">Tenants</a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Monthly recurring revenue" :value="\App\Support\Money::short($mrr)" :note="'ARR '.\App\Support\Money::short($mrr * 12)" tone="good" />
        <x-kpi label="Active tenants" :value="(string) (($statusCounts['active'] ?? 0) + ($statusCounts['trial'] ?? 0))" :note="'+'.$newThisMonth.' sign-ups this month'" tone="good" />
        <x-kpi label="Trials ending in 7 days" :value="(string) $trialsEnding" tone="warn" />
        <x-kpi label="Failed payments (30 days)" :value="(string) $failedPayments->count()" :note="\App\Support\Money::inr($failedPayments->sum('total'))" :tone="$failedPayments->isNotEmpty() ? 'bad' : 'muted'" />
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="card-pad lg:col-span-2">
            <h2 class="section-title mb-3">Subscription collections · 12 months</h2>
            <div class="h-56"><canvas data-chart="line" data-source="rev" role="img" aria-label="Monthly subscription collections"></canvas></div>
            <script type="application/json" id="rev">@json($revenueChart)</script>
        </section>
        <section class="card-pad">
            <h2 class="section-title mb-3">Plan mix</h2>
            @foreach ($planMix as $plan)
                <div class="mb-3"><div class="flex justify-between text-[13.5px]"><span class="font-semibold">{{ $plan->name }}</span><span class="num text-ink-muted">{{ $plan->tenants_count }} tenants</span></div>
                <div class="mt-1.5 h-2.5 rounded bg-[#F1F2F4]"><span class="block h-2.5 rounded bg-teal" style="width: {{ round($plan->tenants_count / $maxPlan * 100) }}%"></span></div></div>
            @endforeach
            <h2 class="section-title mb-2 mt-5">Tenants by status</h2>
            @foreach (\App\Enums\SubscriptionStatus::cases() as $s)
                <div class="flex justify-between py-1 text-sm"><x-status :status="$s" /><span class="num font-semibold">{{ $statusCounts[$s->value] ?? 0 }}</span></div>
            @endforeach
        </section>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="card-pad overflow-x-auto lg:col-span-2">
            <div class="mb-2 flex items-center justify-between"><h2 class="section-title">Latest tenants</h2><a href="{{ route('platform.tenants.index') }}" class="text-[13px] font-semibold">View all {{ $totalTenants }}</a></div>
            <table class="table min-w-[520px]">
                <thead><tr><th>Tenant</th><th>Plan</th><th>Status</th><th>Joined</th><th></th></tr></thead>
                <tbody>
                @foreach ($tenants as $t)
                    <tr><td><span class="block font-semibold">{{ $t->name }}</span><span class="text-xs text-ink-muted">{{ $t->email }}</span></td><td>{{ $t->plan?->name }}</td><td><x-status :status="$t->status" /></td><td>{{ $t->created_at->format('j M Y') }}</td><td class="text-right"><a href="{{ route('platform.tenants.show', $t) }}" class="font-semibold">Manage</a></td></tr>
                @endforeach
                </tbody>
            </table>
        </section>
        <section class="card-pad">
            <h2 class="section-title mb-2">Business activity (all tenants)</h2>
            <p class="mb-2 text-xs text-ink-muted">Aggregates only — no customer data.</p>
            @foreach (\App\Enums\PlotStatus::cases() as $s)
                <div class="flex justify-between py-1 text-sm"><span>{{ $s->label() }}</span><span class="num font-semibold">{{ number_format($plotTotals[$s->value] ?? 0) }}</span></div>
            @endforeach
            <h2 class="section-title mb-2 mt-5">System health</h2>
            <div class="flex justify-between py-1 text-sm"><span>Queue backlog</span><span class="{{ $health['queue'] > 50 ? 'badge-bk' : 'badge-av' }}">{{ $health['queue'] }} jobs</span></div>
            <div class="flex justify-between py-1 text-sm"><span>Failed jobs (24 h)</span><span class="{{ $health['failed_jobs'] ? 'badge-od' : 'badge-av' }}">{{ $health['failed_jobs'] }}</span></div>
            <div class="flex justify-between py-1 text-sm"><span>Failed notifications (24 h)</span><span class="{{ $health['notifications_failed'] ? 'badge-bk' : 'badge-av' }}">{{ $health['notifications_failed'] }}</span></div>
        </section>
    </div>
</x-layouts.platform>
