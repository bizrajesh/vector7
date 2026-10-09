<x-layouts.workspace :title="'Sale '.$sale->sale_no">
    <x-page-header :title="'Sale '.$sale->sale_no" :subtitle="'Plot '.$sale->plot->plot_no.' · '.$sale->project->name.' · '.$sale->customer->name" :back="route('ws.sales.index')">
        @if ($sale->status === 'ror' && ! $sale->registration)@can('registration.create')<a href="{{ route('ws.registrations.create', $sale) }}" class="btn-teal">Start registration</a>@endcan @endif
        @if ($sale->registration)<a href="{{ route('ws.registrations.show', $sale->registration) }}" class="btn-light">Registration</a>@endif
    </x-page-header>
    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        <x-stat label="Net price" :value="\App\Support\Format::inr($sale->net_price)" icon="tag" tone="navy" />
        <x-stat label="Paid" :value="\App\Support\Format::inr($sale->paid_amount)" icon="rupee" />
        <x-stat label="Due" :value="\App\Support\Format::inr($sale->due_amount)" icon="clock" :tone="$sale->due_amount > 0 ? 'gold' : 'teal'" />
        <x-stat label="Complete by" :value="\App\Support\Format::date($sale->window_end)" icon="calendar" :tone="$sale->isWindowMissed() ? 'red' : 'teal'" />
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="flex items-center justify-between p-4"><h2 class="section-title">Instalments</h2><x-status :status="$sale->plot->status" /></div>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Instalment</th><th>Due date</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th></tr></thead>
                    <tbody>@foreach ($sale->instalments as $i)
                        <tr><td>{{ $i->name }} ({{ (float) $i->percent }}%)</td><td>@date($i->due_date)</td><td class="num">@inr($i->amount)</td><td class="num">@inr($i->paid_amount)</td><td class="num">@inr($i->balance())</td>
                            <td><span class="{{ $i->status === 'paid' ? 'badge-teal' : ($i->due_date->isPast() ? 'badge-red' : 'badge-amber') }}">{{ $i->status === 'paid' ? 'Paid' : ($i->due_date->isPast() ? 'Overdue' : ucfirst($i->status)) }}</span></td></tr>
                    @endforeach</tbody>
                </table></div>
            </div>
            <div class="card">
                <h2 class="section-title p-4">Payments for this plot</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Date</th><th>Receipt / txn</th><th>Mode</th><th class="num">Amount</th><th></th></tr></thead>
                    <tbody>@forelse ($sale->payments as $p)
                        <tr><td>@date($p->paid_on)</td><td class="font-mono text-xs">{{ $p->transaction_no }} <span class="badge-gray">{{ $p->kind }}</span></td><td>{{ \App\Models\Payment::MODES[$p->mode] }} {{ $p->reference_no }}</td><td class="num font-bold">@inr($p->amount)</td>
                            <td class="whitespace-nowrap text-right"><a href="{{ route('ws.payments.receipt', $p) }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">Receipt</a>@if ($p->proof)<a href="{{ route('files.show', $p->proof) }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">Proof</a>@endif</td></tr>
                    @empty<tr><td colspan="5" class="text-muted">No payments.</td></tr>@endforelse</tbody>
                </table></div>
            </div>
        </div>
        <div class="space-y-6">
            @if ($sale->status === 'sale_init')
                @can('accounts.create')
                    <form method="POST" action="{{ route('ws.payments.store', $sale) }}" enctype="multipart/form-data" class="card card-pad space-y-3">
                        @csrf
                        <h2 class="section-title">Record a payment</h2>
                        <p class="text-xs text-muted">Only from {{ $sale->customer->name }} for Plot {{ $sale->plot->plot_no }}. Balance @inr($sale->due_amount).</p>
                        <x-field name="amount" type="number" step="0.01" label="Amount (₹)" required :value="$sale->nextDue()?->balance()" />
                        <x-field name="paid_on" type="date" label="Paid on" required :value="today()->toDateString()" />
                        <x-select name="mode" label="Mode" :options="\App\Models\Payment::MODES" />
                        <x-field name="reference_no" label="Reference no." />
                        <x-field name="proof" type="file" label="Proof" accept=".pdf,.jpg,.jpeg,.png,.webp" />
                        <button class="btn-primary w-full">Record payment & email receipt</button>
                    </form>
                @endcan
            @endif
            @if ($quote)
                <div class="card card-pad ring-2 ring-red-200">
                    <h2 class="section-title text-red-800">Sale window missed</h2>
                    <p class="mt-1 text-sm">{{ $quote['days_late'] }} day(s) after the window. If the customer asks for a refund:</p>
                    <dl class="mt-3 space-y-1 text-sm"><div class="flex justify-between"><dt>Paid</dt><dd>@inr($quote['paid'])</dd></div><div class="flex justify-between"><dt>Penalty</dt><dd>− @inr($quote['penalty'])</dd></div><div class="flex justify-between font-bold"><dt>Refund</dt><dd>@inr($quote['refund'])</dd></div></dl>
                    @can('refunds.create')<x-confirm :action="route('ws.refunds.store', $sale)" message="Send a refund request of {{ \App\Support\Format::inr($quote['refund']) }} for Admin approval?" class="btn-danger mt-3 w-full">Request refund</x-confirm>@endcan
                </div>
            @endif
            @if ($sale->refund)
                <div class="card card-pad"><h2 class="section-title">Refund</h2><p class="mt-1 text-sm">{{ ucfirst($sale->refund->status) }} · @inr($sale->refund->refund_amount)</p><a href="{{ route('ws.refunds.note', $sale->refund) }}" class="btn-light btn-sm mt-2" target="_blank" rel="noopener">Refund note</a></div>
            @endif
            <div class="card card-pad text-sm">
                <h2 class="section-title">Details</h2>
                <dl class="mt-2 space-y-1">
                    <div class="flex justify-between"><dt class="text-muted">Actual price</dt><dd>@inr($sale->actual_price)</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Offer price used</dt><dd>{{ $sale->offer_price ? \App\Support\Format::inr($sale->offer_price) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Promo discount</dt><dd>@inr($sale->discount_amount)</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Booking</dt><dd>@if ($sale->booking)<a href="{{ route('ws.bookings.show', $sale->booking) }}">{{ $sale->booking->booking_no }}</a>@else Direct sale @endif</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Broker</dt><dd>{{ $sale->broker ? $sale->broker->name.' · '.\App\Support\Format::inr($sale->broker_commission_amount).' ('.(float) $sale->broker_commission_pct.'%)' : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Disclaimer</dt><dd>{{ \App\Support\Format::datetime($sale->disclaimer_accepted_at) }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</x-layouts.workspace>
