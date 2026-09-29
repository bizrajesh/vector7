@php
    $chart = ['labels' => $events->map(fn ($e) => $e->occurred_at->format('j M'))->prepend('Start')->values(), 'datasets' => [[
        'label' => 'Share value (₹)',
        'data' => $events->pluck('share_value_after')->map(fn ($v) => (float) $v)->prepend((float) ($pool?->face_value ?? 0))->values(),
        'borderColor' => '#C9DDBE', 'backgroundColor' => 'rgba(136,164,124,0.25)', 'pointBackgroundColor' => '#C9DDBE',
    ]]];
    $tabs = [['Home', 'home', route('portal.shareholder'), true], ['Allocations', 'chart', route('portal.shareholder.allocations'), false]];
@endphp
<x-layouts.portal title="My shares" :tabs="$tabs">
    <header class="flex flex-col gap-3.5 bg-navy px-5 pb-5 pt-4 text-white">
        <div class="flex items-center justify-between">
            @if ($pools->count() > 1)
                <form method="GET"><label for="pool" class="sr-only">Project</label>
                    <select id="pool" name="pool" class="h-10 rounded-full border border-white/20 bg-white/5 pl-3.5 pr-8 text-[13.5px] font-semibold text-white" data-autosubmit>
                        @foreach ($pools as $p)<option value="{{ $p->id }}" @selected($p->id === $pool?->id) class="text-ink">{{ $p->layout->name }}</option>@endforeach
                    </select><noscript><button class="text-sm">Go</button></noscript>
                </form>
            @else
                <span class="rounded-full border border-white/20 px-3.5 py-2 text-[13.5px] font-semibold">{{ $pool?->layout->name ?? 'No project yet' }}</span>
            @endif
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-navy-600 text-[13px] font-bold">{{ auth()->user()->initials() }}</span>
        </div>
        @if ($pool)
            <div>
                <p class="text-[13px] text-navy-200">Share value</p>
                <p class="flex items-baseline gap-2.5"><span class="num text-4xl font-extrabold tracking-tight">₹{{ number_format((float) $pool->current_share_value, 2) }}</span>
                    <span class="rounded-full bg-sage/30 px-2.5 py-1 text-[13px] font-bold text-[#C9DDBE]">{{ $pool->growthPct() >= 0 ? '▲' : '▼' }} {{ abs($pool->growthPct()) }}%</span></p>
                <p class="text-[12.5px] text-navy-200">Face value ₹{{ number_format((float) $pool->face_value) }} · moves only when a plot is sold</p>
            </div>
            <div class="h-32"><canvas data-chart="line" data-source="sv" role="img" aria-label="Share value after each plot sale"></canvas></div>
            <script type="application/json" id="sv">@json($chart)</script>
        @endif
    </header>

    <div class="flex flex-col gap-3 px-4 py-4">
        @if ($mine)
            <div class="card grid grid-cols-3 gap-2 px-4 py-3.5">
                <div><p class="text-[11.5px] text-ink-muted">My shares</p><p class="num text-[17px] font-bold">{{ number_format($mine->shares, 2) }}</p><p class="text-[11.5px] text-ink-muted">{{ number_format($mine->pct, 2) }}% of pool</p></div>
                <div><p class="text-[11.5px] text-ink-muted">Contributed</p><p class="num text-[17px] font-bold">@inrShort($mine->contributed)</p></div>
                <div><p class="text-[11.5px] text-ink-muted">Current value</p><p class="num text-[17px] font-bold text-[#1F6B45]">@inrShort($mine->value)</p></div>
            </div>
            @php $paidPct = $mine->allocated > 0 ? min(100, round($mine->paid / $mine->allocated * 100)) : 0; @endphp
            <div class="card flex flex-col gap-2.5 px-4 py-3.5">
                <div class="flex justify-between text-[13px]"><span class="text-ink-muted">Allocated from sales</span><span class="num font-bold">@inr($mine->allocated)</span></div>
                <div class="flex h-2 overflow-hidden rounded-full bg-line-soft"><span class="bg-teal" style="width: {{ $paidPct }}%"></span><span class="bg-sage" style="width: {{ 100 - $paidPct }}%"></span></div>
                <div class="flex justify-between text-[12.5px]"><span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-teal"></span>Paid out @inr($mine->paid)</span><span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-sage"></span>Balance @inr($mine->allocated - $mine->paid)</span></div>
            </div>
            @if ($projected)
                <p class="px-1 text-[12.5px] text-ink-muted">If every unsold plot sells at list price, the share value would be about <strong class="text-ink">₹{{ number_format($projected, 2) }}</strong>.</p>
            @endif
            <section>
                <div class="mb-2 flex items-center justify-between"><h2 class="text-[15px] font-bold">Per-sale allocations</h2><a href="{{ route('portal.shareholder.allocations') }}" class="text-[13px] font-semibold">All</a></div>
                <div class="card">
                    @forelse ($allocations as $a)
                        <div class="flex items-center gap-3 border-t border-line-soft px-4 py-3 first:border-t-0">
                            <span class="flex-1"><span class="block text-sm font-semibold">{{ $a->event->event_type === 'sale' ? 'Plot '.$a->event->plot?->plot_no.' sold' : 'Closing adjustment' }}</span><span class="num text-xs text-ink-muted">{{ $a->event->occurred_at->format('j M') }} · {{ (float) $a->event->profit_per_share >= 0 ? '+' : '' }}₹{{ number_format((float) $a->event->profit_per_share, 2) }} per share</span></span>
                            <span class="num text-sm font-bold {{ (float) $a->allocated_profit >= 0 ? 'text-[#1F6B45]' : 'text-[#A12622]' }}">{{ (float) $a->allocated_profit >= 0 ? '+' : '' }}@inr($a->allocated_profit)</span>
                        </div>
                    @empty
                        <p class="px-4 py-4 text-sm text-ink-muted">Allocations appear here after each plot sale.</p>
                    @endforelse
                </div>
            </section>
        @else
            <div class="card"><x-empty title="No shares allocated yet" icon="pie">Your Admin allocates shares when your contribution is recorded.</x-empty></div>
        @endif
        <p class="flex items-center gap-2 px-1 text-xs text-ink-muted"><x-icon name="lock" class="h-4 w-4 shrink-0" />Shares are allocated by your Admin and can't be bought, sold or transferred.</p>
    </div>
</x-layouts.portal>
