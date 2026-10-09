@extends('pdf.layout', ['docTitle' => 'Project estimate', 'docSub' => $project->project_code.' · '.$estimate->tier.' tier'])
@section('content')
<table class="kv"><tr>
    <td style="width:50%"><h3>{{ $project->name }}</h3>{{ $project->approval_type }} approval · {{ $project->location }}, {{ $project->district }}<br>{{ (float) $project->size_acres }} acres = {{ \App\Support\Format::num($project->totalSqft()) }} sq ft<br>Survey no. {{ $project->survey_numbers }} · Patta {{ $project->patta_numbers }}</td>
    <td style="width:50%" class="right">Total production cost<br><span style="font-size:18px" class="bold">{{ \App\Support\Format::inr($estimate->total_cost) }}</span><br>MRP {{ \App\Support\Format::inr($estimate->mrp_per_sqft, 2) }} / sq ft</td>
</tr></table>
@foreach (['facility' => 'Facilities', 'stage' => 'Approval stages', 'other' => 'Other costs'] as $type => $label)
    @php($rows = $estimate->lines->where('line_type', $type))
    @if ($rows->isNotEmpty())
        <h2>{{ $label }}</h2>
        <table>
            <tr><th>Item</th><th class="right">Qty</th><th>Unit</th><th class="right">Unit cost</th><th class="right">Amount</th></tr>
            @foreach ($rows as $l)
                <tr><td>{{ $l->description }}</td><td class="right">{{ \App\Support\Format::num($l->quantity, 2) }}</td><td>{{ $l->unit }}</td><td class="right">{{ \App\Support\Format::inr($l->unit_cost) }}</td><td class="right">{{ \App\Support\Format::inr($l->amount) }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="4" class="right">{{ $label }} total</td><td class="right">{{ \App\Support\Format::inr($rows->sum('amount')) }}</td></tr>
        </table>
    @endif
@endforeach
<h2>Summary</h2>
<table class="kv">
    <tr><td class="k">Total production cost</td><td class="bold">{{ \App\Support\Format::inr($estimate->total_cost) }}</td></tr>
    <tr><td class="k">Sellable area ({{ (float) $estimate->sellable_pct }}%)</td><td>{{ \App\Support\Format::num($estimate->sellable_sqft) }} sq ft</td></tr>
    <tr><td class="k">Production cost per sellable sq ft</td><td>{{ \App\Support\Format::inr($estimate->cost_per_sqft, 2) }}</td></tr>
    <tr><td class="k">MRP per sq ft (× {{ (float) $estimate->mrp_multiplier }})</td><td class="bold teal">{{ \App\Support\Format::inr($estimate->mrp_per_sqft, 2) }}</td></tr>
    <tr><td class="k">Estimated duration</td><td>{{ $estimate->est_duration_days }} working days (critical path)</td></tr>
</table>
@endsection
