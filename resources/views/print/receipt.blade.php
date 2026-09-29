@php
    $plot = $payment->sale?->plot ?? $payment->booking?->plot;
@endphp
<x-layouts.print :title="'Receipt '.$payment->receipt_no">
    <div class="flex items-start justify-between border-b-2 border-navy pb-4">
        <div><p class="text-xl font-extrabold text-navy">{{ $currentTenant->name ?? config('app.name') }}</p><p class="text-sm text-ink-muted">{{ $currentTenant->address ?? '' }} {{ $currentTenant?->gstin ? '· GSTIN '.$currentTenant->gstin : '' }}</p></div>
        <div class="text-right"><p class="text-lg font-bold">Payment receipt</p><p class="text-sm">{{ $payment->receipt_no }}</p><p class="text-sm">{{ $payment->paid_at->format('j M Y') }}</p></div>
    </div>
    <p class="mt-6">Received with thanks from <strong>{{ $payment->customer->name }}</strong> ({{ $payment->customer->phone }}) the sum of</p>
    <p class="num my-3 text-3xl font-extrabold">@inr($payment->amount, true)</p>
    <table class="table mt-4">
        <tbody>
            <tr><td class="font-semibold">Towards</td><td>{{ $payment->booking_id && ! $payment->sale_id ? 'Booking advance' : 'Plot purchase instalment' }}</td></tr>
            <tr><td class="font-semibold">Project / Plot</td><td>{{ $plot?->layout?->name }} · Plot {{ $plot?->plot_no }} · Survey No. {{ $plot?->survey_no }}</td></tr>
            <tr><td class="font-semibold">Mode</td><td>{{ strtoupper($payment->mode) }} {{ $payment->reference_no ? '· Ref '.$payment->reference_no : '' }}</td></tr>
            @if ($payment->sale)<tr><td class="font-semibold">Balance after this payment</td><td class="num">@inr($payment->sale->sale_value - $payment->sale->payments()->where('id', '<=', $payment->id)->sum('amount'), true)</td></tr>@endif
        </tbody>
    </table>
    <div class="mt-16 flex justify-between text-sm"><span>Customer signature</span><span>Authorised signatory</span></div>
    <p class="mt-10 text-xs text-ink-muted">This is a computer-generated receipt from Vector7.</p>
</x-layouts.print>
