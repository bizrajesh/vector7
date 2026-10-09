<x-layouts.account title="My account">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Active bookings" :value="$bookings->count()" icon="calendar" />
        <x-stat label="Purchases" :value="$sales->count()" icon="cart" tone="navy" />
        <x-stat label="Balance due" :value="\App\Support\Format::inr($sales->where('status', 'sale_init')->sum('due_amount'))" icon="rupee" tone="gold" />
    </div>
    @foreach ($bookings as $b)
        <div class="card card-pad mt-6 ring-2 ring-amber-300">
            <p class="font-bold">Booking {{ $b->booking_no }} — Plot {{ $b->plot->plot_no }}, {{ $b->project->name }}</p>
            <p class="mt-1 text-sm">Pay the first instalment of <strong>@inr($b->firstInstalmentAmount())</strong> by <strong>@date($b->valid_till)</strong> to keep this plot.</p>
        </div>
    @endforeach
    <div class="card mt-6">
        <h2 class="section-title p-4">My plots</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Plot</th><th>Status</th><th class="num">Price</th><th class="num">Paid</th><th class="num">Due</th><th>Next due</th></tr></thead>
            <tbody>
            @forelse ($sales as $s)
                @php($next = $s->nextDue())
                <tr><td><p class="font-semibold">Plot {{ $s->plot->plot_no }}</p><p class="text-xs text-muted">{{ $s->project->name }}</p></td><td><x-status :status="$s->plot->status" /></td>
                    <td class="num">@inr($s->net_price)</td><td class="num">@inr($s->paid_amount)</td><td class="num">@inr($s->due_amount)</td>
                    <td class="text-sm">{{ $next ? \App\Support\Format::inr($next->balance()).' by '.\App\Support\Format::date($next->due_date) : '—' }}</td></tr>
            @empty
                <tr><td colspan="6"><x-empty title="No purchases yet" icon="cart"><a href="{{ route('market.projects') }}">Browse plots</a></x-empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @if ($recentPayments->isNotEmpty())
        <div class="card mt-6"><h2 class="section-title p-4">Recent payments</h2>
            <ul class="divide-y divide-navy-50">@foreach ($recentPayments as $p)<li class="flex items-center justify-between gap-3 p-4 text-sm"><span>@date($p->paid_on) · Plot {{ $p->plot->plot_no }} · {{ \App\Models\Payment::MODES[$p->mode] }}</span><span class="font-bold">@inr($p->amount)</span><a href="{{ route('account.receipt', $p->id) }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">Receipt</a></li>@endforeach</ul>
        </div>
    @endif
</x-layouts.account>
