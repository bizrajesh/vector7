<x-layouts.workspace title="Receipts">
    <x-page-header title="Receipts (income)" subtitle="Every booking and sales payment, with its receipt number.">
        @can('accounts.export')<x-export-buttons />@endcan
    </x-page-header>
    @include('ws.accounts._nav')
    <x-filters>
        <x-select name="project" label="Project" :options="$projects" :value="request('project')" placeholder="All" />
        <x-select name="kind" label="Type" :options="['booking' => 'Booking receipt', 'sale' => 'Sales receipt']" :value="request('kind')" placeholder="All" />
        <x-select name="mode" label="Mode" :options="\App\Models\Payment::MODES" :value="request('mode')" placeholder="All" />
        <x-field name="from" type="date" label="From" :value="request('from')" />
        <x-field name="to" type="date" label="To" :value="request('to')" />
    </x-filters>
    <div class="card">
        <div class="flex items-center justify-between p-4"><p class="text-sm text-muted">{{ number_format($payments->total()) }} receipt(s)</p><p class="text-lg font-extrabold tabular-nums">Total @inr($total)</p></div>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Receipt</th><th>Date</th><th>Type</th><th>Plot</th><th>Customer</th><th>Mode</th><th class="num">Amount</th><th><span class="sr-only">PDF</span></th></tr></thead>
            <tbody>
            @forelse ($payments as $p)
                <tr>
                    <td class="font-mono text-xs font-semibold">{{ $p->transaction_no }}</td>
                    <td class="whitespace-nowrap">@date($p->paid_on)</td>
                    <td><span class="{{ $p->kind === 'booking' ? 'badge-blue' : 'badge-teal' }}">{{ $p->kind === 'booking' ? 'Booking' : 'Sale' }}</span></td>
                    <td>Plot {{ $p->plot->plot_no }}<p class="text-xs text-muted">{{ $p->project->name }}</p></td>
                    <td>{{ $p->customer->name }}</td>
                    <td>{{ \App\Models\Payment::MODES[$p->mode] ?? $p->mode }}@if ($p->reference_no)<p class="text-xs text-muted">{{ $p->reference_no }}</p>@endif</td>
                    <td class="num font-semibold">@inr($p->amount)</td>
                    <td>@can('sales.view')<a href="{{ route('ws.payments.receipt', $p) }}" class="btn-ghost btn-sm" aria-label="Receipt PDF for {{ $p->transaction_no }}"><x-icon name="download" class="h-4 w-4" /></a>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty title="No receipts match these filters" icon="receipt" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-4">{{ $payments->links() }}</div>
    </div>
</x-layouts.workspace>
