@php $plot = $registration->plot; $customer = $registration->sale->customer; @endphp
<x-layouts.print :title="'Registration details · '.$plot->plot_no">
    <h1 class="text-2xl font-extrabold text-navy">Registration details</h1>
    <p class="text-sm text-ink-muted">For: {{ $registration->writer?->name ?? $registration->document_writer_name }} · Sub-Registrar office: {{ $registration->office?->name ?? '—' }} · Planned: {{ $registration->planned_date?->format('j M Y') ?? '—' }}</p>
    <table class="table mt-6">
        <tbody>
            <tr><td class="w-1/3 font-semibold">Property location</td><td>{{ $plot->layout->name }}, {{ $plot->layout->village }}, {{ $plot->layout->taluk }}, {{ $plot->layout->district }}</td></tr>
            <tr><td class="font-semibold">Survey No / Plot No</td><td>{{ $plot->survey_no }} / {{ $plot->plot_no }}</td></tr>
            <tr><td class="font-semibold">Extent</td><td>{{ number_format((float) $plot->size_sqft) }} sqft {{ $plot->dimensions ? '('.$plot->dimensions.' ft)' : '' }}</td></tr>
            <tr><td class="font-semibold">Boundaries</td><td>North: {{ $plot->boundary_north }} · South: {{ $plot->boundary_south }} · East: {{ $plot->boundary_east }} · West: {{ $plot->boundary_west }}</td></tr>
            <tr><td class="font-semibold">Buyer</td><td>{{ $customer->name }} · {{ $customer->phone }} · Aadhaar {{ $customer->maskedAadhaar() }} · PAN {{ $customer->maskedPan() }}</td></tr>
            <tr><td class="font-semibold">Seller(s)</td><td>{{ $plot->layout->owners->pluck('name')->implode(', ') }}</td></tr>
            <tr><td class="font-semibold">Sale value</td><td class="num">@inr($registration->sale->sale_value, true) (fully paid)</td></tr>
        </tbody>
    </table>
    <h2 class="mt-8 text-lg font-bold">Checklist</h2>
    <table class="table mt-2">
        <thead><tr><th>#</th><th>Item</th><th>Details</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($registration->items as $item)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $item->label }}</td><td>{{ $item->value }} {{ $item->date_value?->format('j M Y') }}</td><td>{{ $item->is_done ? '✓ Verified' : '☐ Pending' }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="mt-16 grid grid-cols-2 gap-8 text-sm"><span>Verified by: ______________________</span><span>Date: ______________</span></div>
</x-layouts.print>
