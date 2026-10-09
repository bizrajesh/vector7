<x-layouts.workspace title="Refunds">
    <x-page-header title="Refunds" subtitle="Refund = amount paid − penalty from the refund penalty table. Tenant Admin approves with their password." />
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Requested</th><th>Sale / plot</th><th>Customer</th><th class="num">Paid</th><th class="num">Days late</th><th class="num">Penalty</th><th class="num">Refund</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($refunds as $r)
            <tr>
                <td class="text-sm">@date($r->created_at)</td>
                <td><a href="{{ route('ws.sales.show', $r->sale) }}" class="font-mono text-xs">{{ $r->sale->sale_no }}</a><p class="text-xs text-muted">Plot {{ $r->sale->plot->plot_no }} · {{ $r->sale->project->name }}</p></td>
                <td>{{ $r->customer->name }}</td>
                <td class="num">@inr($r->paid_amount)</td><td class="num">{{ $r->days_late }}</td><td class="num">@inr($r->penalty_amount)</td><td class="num font-bold">@inr($r->refund_amount)</td>
                <td><span class="{{ ['pending' => 'badge-amber', 'approved' => 'badge-teal', 'rejected' => 'badge-gray'][$r->status] }}">{{ ucfirst($r->status) }}</span></td>
                <td class="whitespace-nowrap text-right">
                    <a href="{{ route('ws.refunds.note', $r) }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">Note</a>
                    @if ($r->status === 'pending' && auth()->user()->isTenantAdmin())
                        <form method="POST" action="{{ route('ws.refunds.decide', $r) }}" class="inline" data-confirm="Approve a refund of {{ \App\Support\Format::inr($r->refund_amount) }}? The plot becomes available again." data-confirm-password>@csrf<input type="hidden" name="current_password"><button name="decision" value="approve" class="btn-teal btn-sm">Approve</button></form>
                        <form method="POST" action="{{ route('ws.refunds.decide', $r) }}" class="inline" data-confirm="Reject this refund request?" data-confirm-password>@csrf<input type="hidden" name="current_password"><button name="decision" value="reject" class="btn-light btn-sm">Reject</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty title="No refund requests" icon="refresh" /></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $refunds->links() }}</div></div>
</x-layouts.workspace>
