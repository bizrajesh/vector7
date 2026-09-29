<div class="card-pad">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="section-title">Shareholders</h2>
        @if ($layout->sharePool)
            <a href="{{ route('app.shares.show', $layout->sharePool) }}" class="btn-outline btn-sm">Manage share pool</a>
        @elseif (auth()->user()->can('shares.allocate'))
            <form method="POST" action="{{ route('app.shares.pools.store', $layout) }}">@csrf<button class="btn-primary btn-sm">Create share pool</button></form>
        @endif
    </div>
    @if ($holdings->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="table min-w-[520px]">
                <thead><tr><th>Shareholder</th><th>Shares</th><th>Holding</th><th>Contributed</th><th>Current value</th></tr></thead>
                <tbody>
                @foreach ($holdings as $h)
                    <tr><td class="font-semibold">{{ $h->shareholder->name }}</td><td class="num">{{ number_format($h->shares, 4) }}</td><td class="num">{{ number_format($h->pct, 2) }}%</td><td class="num">@inr($h->contributed)</td><td class="num font-semibold">@inr($h->value)</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-ink-muted">No shares allocated yet. Shareholders are allocated shares by contribution; share value then moves only when plots are sold.</p>
    @endif
</div>
