<x-layouts.workspace :title="$c->name">
    <x-page-header :title="$c->name" :subtitle="$c->email.' · '.$c->mobile.($c->city ? ' · '.$c->city : '')" :back="route('ws.customers.index')" />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Bookings" :value="$bookings->count()" icon="calendar" />
        <x-stat label="Purchases" :value="$sales->count()" icon="cart" tone="navy" />
        <x-stat label="Paid to you" :value="\App\Support\Format::inr($payments->sum('amount'))" icon="rupee" tone="gold" />
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card">
            <h2 class="section-title p-4">Bookings</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Booking</th><th>Plot</th><th class="num">Net price</th><th>Status</th></tr></thead>
                <tbody>@forelse ($bookings as $b)<tr><td>@can('bookings.view')<a href="{{ route('ws.bookings.show', $b) }}" class="font-mono text-xs">{{ $b->booking_no }}</a>@else<span class="font-mono text-xs">{{ $b->booking_no }}</span>@endcan<p class="text-xs text-muted">@date($b->booked_on)</p></td><td>Plot {{ $b->plot?->plot_no }} · {{ $b->project?->name }}</td><td class="num">@inr($b->net_price)</td><td><span class="badge-gray">{{ \App\Models\Booking::STATUSES[$b->status] ?? $b->status }}</span></td></tr>@empty<tr><td colspan="4" class="text-sm text-muted">No bookings.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
        <div class="card">
            <h2 class="section-title p-4">Purchases</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Sale</th><th>Plot</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th></tr></thead>
                <tbody>@forelse ($sales as $s)<tr><td>@can('sales.view')<a href="{{ route('ws.sales.show', $s) }}" class="font-mono text-xs">{{ $s->sale_no }}</a>@else<span class="font-mono text-xs">{{ $s->sale_no }}</span>@endcan</td><td>Plot {{ $s->plot?->plot_no }} · {{ $s->project?->name }}</td><td class="num">@inr($s->paid_amount)</td><td class="num">@inr($s->due_amount)</td><td><span class="badge-navy">{{ \App\Models\Sale::STATUSES[$s->status] ?? $s->status }}</span></td></tr>@empty<tr><td colspan="5" class="text-sm text-muted">No purchases.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
        <div class="card">
            <h2 class="section-title p-4">Payments</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Date</th><th>Receipt</th><th>Plot</th><th>Mode</th><th class="num">Amount</th></tr></thead>
                <tbody>@forelse ($payments as $p)<tr><td>@date($p->paid_on)</td><td class="font-mono text-xs">{{ $p->receipt?->receipt_no ?? $p->transaction_no }}</td><td>{{ $p->plot?->plot_no }}</td><td>{{ ucfirst($p->mode) }}</td><td class="num">@inr($p->amount)</td></tr>@empty<tr><td colspan="5" class="text-sm text-muted">No payments.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
        <div class="card">
            <h2 class="section-title p-4">Registrations</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Plot</th><th>Date</th><th>Document no.</th><th>Status</th></tr></thead>
                <tbody>@forelse ($registrations as $r)<tr><td>Plot {{ $r->plot?->plot_no }} · {{ $r->project?->name }}</td><td>@date($r->registration_date)</td><td>{{ $r->registered_doc_no ?: '—' }}</td><td><span class="badge-purple">{{ ucwords(str_replace('_', ' ', $r->status)) }}</span></td></tr>@empty<tr><td colspan="4" class="text-sm text-muted">No registrations.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
    </div>
</x-layouts.workspace>
