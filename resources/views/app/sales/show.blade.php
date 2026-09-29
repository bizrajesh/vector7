<x-layouts.app :title="'Sale · '.$sale->plot->plot_no">
    <x-page-header :title="$sale->customer->name.' · '.$sale->plot->plot_no" :subtitle="$sale->plot->layout->name.' · sold '.$sale->sale_date->format('j M Y').' · complete by '.$sale->due_by->format('j M Y')" :back="route('app.sales.index')">
        <x-slot:actions><x-status :status="$sale->plot->status" />@if ($sale->isOverdue())<span class="badge-od">Overdue</span>@endif</x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card-pad lg:col-span-2">
            <div class="flex items-baseline justify-between"><h2 class="section-title">Instalments</h2><span class="num text-sm text-ink-muted">@inr($sale->paid_amount) of @inr($sale->sale_value)</span></div>
            <div class="progress mt-2"><span style="width: {{ $sale->sale_value > 0 ? min(100, round($sale->paid_amount / $sale->sale_value * 100)) : 0 }}%"></span></div>
            <div class="mt-3 flex flex-col">
                @foreach ($sale->instalments as $i)
                    @php $late = $i->status !== 'paid' && $i->due_date->lt(now()->startOfDay()); @endphp
                    <div class="flex items-center gap-3 border-t border-line-soft py-3 first:border-t-0">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] {{ $i->status === 'paid' ? 'bg-[#E3F2EA] text-[#1F6B45]' : ($late ? 'bg-[#FBE4E2] text-[#A12622]' : 'bg-[#FBEFD5] text-[#7A4F00]') }}"><x-icon :name="$i->status === 'paid' ? 'check' : 'clock'" class="h-[18px] w-[18px]" stroke="2.2" /></span>
                        <span class="flex-1"><span class="block text-sm font-semibold">Instalment {{ $i->seq }} · {{ (float) $i->pct }}%</span><span class="text-xs {{ $late ? 'font-semibold text-[#A12622]' : 'text-ink-muted' }}">{{ $i->status === 'paid' ? 'Paid' : 'Due '.$i->due_date->format('j M Y') }}{{ $i->status === 'partial' ? ' · paid '.\App\Support\Money::inr($i->paid_amount) : '' }}</span></span>
                        <span class="num text-sm font-bold">@inr($i->amount)</span>
                    </div>
                @endforeach
            </div>
            @if ($sale->broker)<p class="mt-3 text-sm text-ink-muted">Broker {{ $sale->broker->name }} · {{ (float) $sale->commission_pct }}% = @inr($sale->commission_amount)</p>@endif
        </section>

        <section class="flex flex-col gap-4">
            @if ($sale->status === 'ongoing' && auth()->user()->can('payments.record'))
                <form method="POST" action="{{ route('app.sales.payments.store', $sale) }}" class="card-pad flex flex-col gap-3" data-confirm="Record this payment?">@csrf
                    <h2 class="section-title">Record payment</h2>
                    <p class="text-sm text-ink-muted">Balance <strong class="num text-ink">@inr($sale->balance())</strong></p>
                    @include('app._payment-fields')
                    <x-field name="notes" label="Notes" />
                    <button class="btn-primary">Save & issue receipt</button>
                </form>
            @endif
            @if ($sale->registration)
                <a href="{{ route('app.registrations.show', $sale->registration) }}" class="btn-outline">Open registration</a>
            @elseif ($sale->status === 'paid')
                <a href="{{ route('app.plots.show', $sale->plot) }}" class="btn-primary">Initiate registration</a>
            @endif
            @if ($sale->status === 'ongoing' && auth()->user()->hasRole('admin'))
                <details class="card-pad"><summary class="cursor-pointer text-sm font-semibold text-red-700">Cancel sale</summary>
                    <form method="POST" action="{{ route('app.sales.cancel', $sale) }}" class="mt-3 flex flex-col gap-2" data-confirm="Cancel this sale and release the plot?">@csrf
                        <x-field name="reason" label="Reason" required />
                        <button class="btn-danger btn-sm">Cancel sale</button>
                        <p class="help">Record any refund in Accounting.</p>
                    </form>
                </details>
            @endif
        </section>

        <section class="card-pad lg:col-span-3">
            <h2 class="section-title mb-2">Payments</h2>
            <div class="overflow-x-auto">
                <table class="table min-w-[560px]">
                    <thead><tr><th>Receipt</th><th>Date</th><th>Mode</th><th>Reference</th><th>Amount</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($sale->payments as $p)
                        <tr><td class="font-semibold">{{ $p->receipt_no }}</td><td>{{ $p->paid_at->format('j M Y') }}</td><td>{{ strtoupper($p->mode) }}</td><td>{{ $p->reference_no ?? '—' }}</td><td class="num font-semibold">@inr($p->amount)</td>
                            <td class="text-right"><a href="{{ route('app.payments.receipt', $p) }}" class="inline-flex items-center gap-1 text-sm font-semibold"><x-icon name="print" class="h-4 w-4" />Receipt</a></td></tr>
                    @empty
                        <tr><td colspan="6" class="text-ink-muted">No payments yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
