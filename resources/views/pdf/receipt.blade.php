@extends('pdf.layout', ['docTitle' => 'Payment receipt', 'docSub' => 'Receipt no. '.$payment->receipt?->receipt_no])
@section('content')
<table class="kv"><tr>
    <td style="width:55%"><h3>Received from</h3><span class="bold">{{ $payment->customer->name }}</span><br>{{ $payment->customer->mobile }} · {{ $payment->customer->email }}@if ($payment->customer->address)<br>{{ $payment->customer->address }}@endif</td>
    <td style="width:45%" class="right"><h3>Receipt</h3>No. {{ $payment->receipt?->receipt_no }}<br>Date {{ $payment->paid_on->format('d-m-Y') }}<br>Sale {{ $sale->sale_no }}</td>
</tr></table>
<div class="box">
    <table class="kv">
        <tr><td class="k">Amount received</td><td style="font-size:16px" class="bold">{{ \App\Support\Format::inr($payment->amount, 2) }}</td></tr>
        <tr><td class="k">In words</td><td>{{ \App\Support\Format::words((float) $payment->amount) }}</td></tr>
        <tr><td class="k">Mode</td><td>{{ \App\Models\Payment::MODES[$payment->mode] }}{{ $payment->reference_no ? ' · Ref. '.$payment->reference_no : '' }}</td></tr>
        <tr><td class="k">Towards</td><td>Plot {{ $sale->plot->plot_no }}, {{ $sale->project->name }}, {{ $sale->project->location }} — {{ \App\Support\Format::num($sale->plot->size_sqft) }} sq ft, Patta {{ $sale->plot->patta_number }}</td></tr>
    </table>
</div>
<h2>Account</h2>
<table>
    <tr><th>Date</th><th>Receipt</th><th>Mode</th><th class="right">Amount</th></tr>
    @foreach ($sale->payments as $p)
        <tr><td>{{ $p->paid_on->format('d-m-Y') }}</td><td>{{ $p->transaction_no }}</td><td>{{ \App\Models\Payment::MODES[$p->mode] }}</td><td class="right">{{ \App\Support\Format::inr($p->amount, 2) }}</td></tr>
    @endforeach
    <tr class="total"><td colspan="3" class="right">Plot price {{ \App\Support\Format::inr($sale->net_price, 2) }} · Paid</td><td class="right">{{ \App\Support\Format::inr($sale->paid_amount, 2) }}</td></tr>
    <tr><td colspan="3" class="right bold">Balance due</td><td class="right bold">{{ \App\Support\Format::inr($sale->due_amount, 2) }}</td></tr>
</table>
<table class="sign"><tr><td style="width:60%"></td><td class="center">For {{ $tenant->name }}<br><br><br>Authorised signatory</td></tr></table>
@if ($disclaimer)<div class="disclaimer"><strong>Terms:</strong> {{ $disclaimer }}</div>@endif
@endsection
