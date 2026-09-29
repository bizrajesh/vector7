<x-layouts.app title="Manage Shares">
    <x-page-header title="Manage Shares" subtitle="Share value moves only when plots are sold. Shareholders cannot buy, sell or transfer shares.">
        <x-slot:actions><a href="{{ route('app.shares.shareholders') }}" class="btn-ghost btn-sm"><x-icon name="users" class="h-4 w-4" />Shareholders</a></x-slot:actions>
    </x-page-header>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($pools as $pool)
            <a href="{{ route('app.shares.show', $pool) }}" class="card-pad flex flex-col gap-2 text-ink no-underline hover:shadow-card hover:text-ink">
                <span class="kpi-label">{{ $pool->layout->name }}</span>
                <span class="kpi-value">₹{{ number_format((float) $pool->current_share_value, 2) }}</span>
                <span class="text-[12.5px] font-semibold {{ $pool->growthPct() >= 0 ? 'text-[#1F6B45]' : 'text-[#A12622]' }}">{{ $pool->growthPct() >= 0 ? '▲' : '▼' }} {{ abs($pool->growthPct()) }}% over face value ₹{{ number_format((float) $pool->face_value) }}</span>
                <span class="text-xs text-ink-muted">{{ number_format((float) $pool->total_shares, 2) }} shares · capital @inrShort($pool->total_capital) · {{ $pool->events_count }} sale events</span>
            </a>
        @endforeach
    </div>
    @if ($layoutsWithoutPool->isNotEmpty() && auth()->user()->can('shares.allocate'))
        <div class="card-pad mt-5">
            <h2 class="section-title mb-2">Projects without a share pool</h2>
            @foreach ($layoutsWithoutPool as $layout)
                <form method="POST" action="{{ route('app.shares.pools.store', $layout) }}" class="flex items-center gap-3 border-t border-line-soft py-2.5 first:border-t-0">@csrf
                    <span class="flex-1 text-sm font-semibold">{{ $layout->name }} <span class="font-normal text-ink-muted">· {{ $layout->code }}</span></span>
                    <button class="btn-outline btn-sm">Create pool</button>
                </form>
            @endforeach
        </div>
    @endif
</x-layouts.app>
