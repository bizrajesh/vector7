@php $tabs = [['Home', 'home', route('portal.shareholder'), false], ['Allocations', 'chart', route('portal.shareholder.allocations'), true]]; @endphp
<x-layouts.portal title="Allocations" :tabs="$tabs">
    <div class="px-4 py-5">
        <h1 class="text-xl font-bold">Allocations & payouts</h1>
        <h2 class="mt-5 text-[15px] font-bold">Credited from plot sales</h2>
        <div class="card mt-2">
            @forelse ($allocations as $a)
                <div class="flex items-center gap-3 border-t border-line-soft px-4 py-3 first:border-t-0">
                    <span class="flex-1"><span class="block text-sm font-semibold">{{ $a->event->plot ? 'Plot '.$a->event->plot->plot_no : 'Closing adjustment' }} · {{ $a->event->plot?->layout?->name }}</span><span class="num text-xs text-ink-muted">{{ $a->event->occurred_at->format('j M Y') }} · {{ number_format((float) $a->shares_held, 2) }} shares ({{ number_format((float) $a->holding_pct, 2) }}%)</span></span>
                    <span class="num text-sm font-bold">@inr($a->allocated_profit)</span>
                </div>
            @empty
                <p class="px-4 py-4 text-sm text-ink-muted">No allocations yet.</p>
            @endforelse
        </div>
        <div class="mt-3">{{ $allocations->links() }}</div>
        <h2 class="mt-6 text-[15px] font-bold">Payouts received</h2>
        <div class="card mt-2">
            @forelse ($payouts as $p)
                <div class="flex items-center gap-3 border-t border-line-soft px-4 py-3 first:border-t-0">
                    <span class="flex-1"><span class="block text-sm font-semibold">{{ $p->pool->layout->name }}</span><span class="text-xs text-ink-muted">{{ $p->paid_on->format('j M Y') }} · {{ strtoupper($p->mode) }} {{ $p->reference_no }}</span></span>
                    <span class="num text-sm font-bold">@inr($p->amount)</span>
                </div>
            @empty
                <p class="px-4 py-4 text-sm text-ink-muted">No payouts yet.</p>
            @endforelse
        </div>
    </div>
</x-layouts.portal>
