@php
    $user = auth()->user();
    $chart = ['labels' => $pool->events()->orderBy('occurred_at')->get()->map(fn ($e) => $e->occurred_at->format('j M'))->prepend('Start')->values(), 'datasets' => [[
        'label' => 'Share value (₹)',
        'data' => $pool->events()->orderBy('occurred_at')->pluck('share_value_after')->map(fn ($v) => (float) $v)->prepend((float) $pool->face_value)->values(),
    ]]];
@endphp
<x-layouts.app :title="'Shares · '.$pool->layout->name">
    <x-page-header :title="$pool->layout->name" subtitle="Share pool" :back="route('app.shares.index')" />

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Share value" :value="'₹'.number_format((float) $pool->current_share_value, 2)" :note="($pool->growthPct() >= 0 ? '▲ ' : '▼ ').abs($pool->growthPct()).'% vs face ₹'.number_format((float) $pool->face_value)" :tone="$pool->growthPct() >= 0 ? 'good' : 'bad'" />
        <x-kpi label="Total shares" :value="number_format((float) $pool->total_shares, 2)" />
        <x-kpi label="Contributed capital" :value="\App\Support\Money::short($pool->total_capital)" />
        <x-kpi label="Realised profit" :value="\App\Support\Money::short($pool->cumulative_profit)" :note="'Baseline ₹'.number_format((float) $pool->baseline_cost_per_sqft, 2).'/ft²'" />
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="card-pad lg:col-span-2">
            <h2 class="section-title mb-3">Share value by sale</h2>
            <div class="h-56"><canvas data-chart="line" data-source="share-chart" aria-label="Share value after each plot sale" role="img"></canvas></div>
            <script type="application/json" id="share-chart">@json($chart)</script>
        </section>
        <section class="flex flex-col gap-4">
            @can('shares.allocate')
                <form method="POST" action="{{ route('app.shares.allocate', $pool) }}" class="card-pad flex flex-col gap-3" data-confirm="Allocate shares for this contribution?">@csrf
                    <h2 class="section-title">Allocate shares</h2>
                    <x-select name="shareholder_id" label="Shareholder" :options="$shareholders->pluck('name', 'id')" placeholder="Choose shareholder" required />
                    <x-select name="contribution_type" label="Contribution" :options="['cash' => 'Cash', 'land' => 'Land (agreed value)', 'reinvest' => 'Reinvested profit']" />
                    <x-field name="contribution_amount" type="number" step="0.01" label="Amount (₹)" required inputmode="decimal" />
                    <x-field name="issued_on" type="date" label="Date" :value="now()->toDateString()" required />
                    <x-field name="reason" label="Reason" :help="$events->isNotEmpty() ? 'Required: sales have started, so shares are issued at the current value.' : 'Shares are issued at face value before the first sale.'" />
                    <button class="btn-primary">Allocate</button>
                </form>
            @endcan
        </section>
    </div>

    <section class="card-pad mt-4">
        <h2 class="section-title mb-2">Holdings</h2>
        <div class="overflow-x-auto">
            <table class="table min-w-[720px]">
                <thead><tr><th>Shareholder</th><th>Shares</th><th>Holding</th><th>Contributed</th><th>Value now</th><th>Allocated</th><th>Paid</th><th>Balance</th></tr></thead>
                <tbody>
                @forelse ($holdings as $h)
                    <tr><td class="font-semibold">{{ $h->shareholder->name }}</td><td class="num">{{ number_format($h->shares, 4) }}</td><td class="num">{{ number_format($h->pct, 2) }}%</td><td class="num">@inr($h->contributed)</td>
                        <td class="num font-semibold">@inr($h->value)</td><td class="num">@inr($h->allocated)</td><td class="num">@inr($h->paid)</td><td class="num font-semibold">@inr($h->allocated - $h->paid)</td></tr>
                @empty
                    <tr><td colspan="8" class="text-ink-muted">No allocations yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @can('shares.payout')
            @if ($holdings->isNotEmpty())
                <form method="POST" action="{{ route('app.shares.payouts.store', $pool) }}" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-3 lg:grid-cols-6" data-confirm="Record this payout?">@csrf
                    <x-select name="shareholder_id" label="Pay to" :options="$holdings->mapWithKeys(fn ($h) => [$h->shareholder_id => $h->shareholder->name])" required />
                    <x-field name="amount" type="number" step="0.01" label="Amount (₹)" required />
                    <x-field name="paid_on" type="date" label="Date" :value="now()->toDateString()" required />
                    <x-select name="mode" label="Mode" :options="['neft' => 'NEFT', 'upi' => 'UPI', 'cheque' => 'Cheque', 'cash' => 'Cash']" />
                    <x-field name="reference_no" label="Reference" />
                    <div class="flex items-end"><button class="btn-outline w-full">Record payout</button></div>
                </form>
            @endif
        @endcan
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="card-pad">
            <h2 class="section-title mb-2">Sale events</h2>
            @forelse ($events as $e)
                <div class="flex items-center gap-3 border-t border-line-soft py-2.5 text-sm first:border-t-0">
                    <span class="flex-1"><span class="block font-semibold">{{ $e->event_type === 'sale' ? 'Plot '.$e->plot?->plot_no.' sold' : 'Closing true-up' }}</span><span class="text-xs text-ink-muted">{{ $e->occurred_at->format('j M Y') }} · {{ (float) $e->profit_per_share >= 0 ? '+' : '' }}₹{{ number_format((float) $e->profit_per_share, 2) }} per share</span></span>
                    <span class="num font-semibold {{ (float) $e->realised_profit >= 0 ? 'text-[#1F6B45]' : 'text-[#A12622]' }}">@inr($e->realised_profit)</span>
                </div>
            @empty
                <p class="text-sm text-ink-muted">Share value changes when the first plot is sold.</p>
            @endforelse
        </section>
        <section class="card-pad">
            <h2 class="section-title mb-2">Allocation ledger</h2>
            @foreach ($issuances as $i)
                <div class="flex items-center gap-3 border-t border-line-soft py-2.5 text-sm first:border-t-0">
                    <span class="flex-1"><span class="block font-semibold">{{ $i->shareholder->name }}</span><span class="text-xs text-ink-muted">{{ $i->issued_on->format('j M Y') }} · {{ ucfirst($i->contribution_type) }} @ ₹{{ number_format((float) $i->issue_price, 2) }} {{ $i->reason ? '· '.$i->reason : '' }}</span></span>
                    <span class="num font-semibold {{ (float) $i->shares < 0 ? 'text-[#A12622]' : '' }}">{{ number_format((float) $i->shares, 4) }}</span>
                    @if ($user->can('shares.allocate') && ! $i->reversal_of && (float) $i->shares > 0)
                        <details class="relative"><summary class="cursor-pointer list-none rounded-lg p-2 text-ink-muted hover:bg-cream-100" aria-label="Reverse allocation"><x-icon name="dots" class="h-4 w-4" /></summary>
                            <form method="POST" action="{{ route('app.shares.reverse', $i) }}" class="absolute right-0 z-10 mt-1 flex w-64 flex-col gap-2 rounded-xl border border-line bg-white p-3 shadow-card" data-confirm="Reverse this allocation?">@csrf
                                <x-field name="reason" label="Reason for reversal" required />
                                <button class="btn-danger btn-sm">Reverse</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </section>
    </div>
</x-layouts.app>
