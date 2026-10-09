@extends('pdf.layout', ['docTitle' => 'Tax invoice', 'docSub' => $invoice->number])
@section('content')
<table class="kv"><tr>
    <td style="width:50%"><h3>Billed to</h3>{{ $invoice->tenant->name }}<br>Tenant ID {{ $invoice->tenant->code }}<br>{{ $invoice->tenant->addressLine() }}<br>{{ $invoice->tenant->email }}</td>
    <td style="width:50%" class="right"><h3>Invoice</h3>{{ $invoice->number }}<br>Date {{ $invoice->created_at->format('d-m-Y') }}<br>Status <span class="badge">{{ strtoupper($invoice->status) }}</span>@if ($invoice->paid_at)<br>Paid {{ $invoice->paid_at->format('d-m-Y') }} ({{ $invoice->method }})@endif</td>
</tr></table>
<h2>Details</h2>
<table>
    <tr><th>Description</th><th>Period</th><th class="right">Amount</th></tr>
    <tr><td>vector7 {{ $invoice->plan->name }} plan ({{ $invoice->plan->billing_cycle }})</td><td>{{ $invoice->period_start->format('d-m-Y') }} – {{ $invoice->period_end->format('d-m-Y') }}</td><td class="right">{{ \App\Support\Format::inr($invoice->amount, 2) }}</td></tr>
    <tr><td colspan="2" class="right">GST {{ \App\Services\SubscriptionService::GST_PCT }}%</td><td class="right">{{ \App\Support\Format::inr($invoice->tax, 2) }}</td></tr>
    <tr class="total"><td colspan="2" class="right">Total</td><td class="right">{{ \App\Support\Format::inr($invoice->total, 2) }}</td></tr>
</table>
<p class="muted small">Amount in words: {{ \App\Support\Format::words((float) $invoice->total) }}</p>
@endsection
