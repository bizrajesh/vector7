@extends('pdf.layout', ['docTitle' => 'Refund note', 'docSub' => 'Sale '.$refund->sale->sale_no])
@section('content')
<table class="kv">
    <tr><td class="k">Customer</td><td class="bold">{{ $refund->customer->name }} · {{ $refund->customer->mobile }}</td></tr>
    <tr><td class="k">Plot</td><td>Plot {{ $refund->sale->plot->plot_no }}, {{ $refund->sale->project->name }}</td></tr>
    <tr><td class="k">Sale completion window ended</td><td>{{ $refund->sale->window_end->format('d-m-Y') }} ({{ $refund->days_late }} day(s) before the request)</td></tr>
    <tr><td class="k">Status</td><td><span class="badge">{{ strtoupper($refund->status) }}</span> @if ($refund->decided_at) on {{ $refund->decided_at->format('d-m-Y') }} @endif</td></tr>
</table>
<h2>Calculation</h2>
<table>
    <tr><td>Amount paid</td><td class="right">{{ \App\Support\Format::inr($refund->paid_amount, 2) }}</td></tr>
    <tr><td>Penalty (refund penalty table)</td><td class="right">− {{ \App\Support\Format::inr($refund->penalty_amount, 2) }}</td></tr>
    <tr class="total"><td>Refund</td><td class="right">{{ \App\Support\Format::inr($refund->refund_amount, 2) }}</td></tr>
</table>
<p class="muted small">{{ \App\Support\Format::words((float) $refund->refund_amount) }}</p>
@if ($refund->decision_notes)<p>Notes: {{ $refund->decision_notes }}</p>@endif
<table class="sign"><tr><td class="center" style="width:50%">Customer signature</td><td class="center">For {{ $tenant->name }}<br>Authorised signatory</td></tr></table>
@if ($disclaimer)<div class="disclaimer"><strong>Refund terms:</strong> {{ $disclaimer }}</div>@endif
@endsection
