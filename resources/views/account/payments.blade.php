<x-layouts.account title="Payments & receipts">
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Date</th><th>Receipt</th><th>Plot</th><th>Mode</th><th class="num">Amount</th><th></th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr><td>@date($p->paid_on)</td><td class="font-mono text-xs">{{ $p->receipt?->receipt_no }}</td><td>Plot {{ $p->plot->plot_no }}<p class="text-xs text-muted">{{ $p->project->name }}</p></td><td>{{ \App\Models\Payment::MODES[$p->mode] }} {{ $p->reference_no }}</td><td class="num font-bold">@inr($p->amount)</td>
                <td class="text-right"><a href="{{ route('account.receipt', $p->id) }}" class="btn-light btn-sm" target="_blank" rel="noopener"><x-icon name="download" class="h-4 w-4" /> PDF</a></td></tr>
        @empty
            <tr><td colspan="6"><x-empty title="No payments yet" icon="receipt" /></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.account>
