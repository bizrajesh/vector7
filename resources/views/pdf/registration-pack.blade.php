@extends('pdf.layout', ['docTitle' => 'Registration pack', 'docSub' => 'Plot '.$r->plot->plot_no.' · '.$r->project->name])
@section('content')
<table class="kv">
    <tr><td class="k">Registration date</td><td class="bold">{{ $r->registration_date?->format('d-m-Y') }}</td></tr>
    <tr><td class="k">Sub-Registrar Office</td><td>{{ $r->sro?->name }}{{ $r->sro?->taluk ? ', '.$r->sro->taluk : '' }}, {{ $r->sro?->district }}</td></tr>
    <tr><td class="k">Document writer</td><td>{{ $r->document_writer ?: '—' }}</td></tr>
    <tr><td class="k">Sale value</td><td>{{ \App\Support\Format::inr($r->sale->net_price, 2) }} (paid in full — {{ $r->sale->payments->count() }} receipt(s))</td></tr>
</table>
<h2>Property (schedule)</h2>
<table class="kv">
    <tr><td class="k">Layout / project</td><td>{{ $r->project->name }} ({{ $r->project->approval_type }} approved)</td></tr>
    <tr><td class="k">Plot no.</td><td class="bold">{{ $r->plot->plot_no }}</td></tr>
    <tr><td class="k">Survey no. / Patta no.</td><td>{{ $r->project->survey_numbers ?? '—' }} / {{ $r->plot->patta_number }}</td></tr>
    <tr><td class="k">PR numbers</td><td>{{ $r->pr_numbers ?: '—' }}</td></tr>
    <tr><td class="k">Extent</td><td>{{ \App\Support\Format::num($r->plot->size_sqft) }} sq ft ({{ $r->plot->cents() }} cents){{ $r->plot->dimensions() ? ' · '.$r->plot->dimensions() : '' }}</td></tr>
    <tr><td class="k">Location</td><td>{{ $r->project->address ?: $r->project->location }}, {{ $r->project->district }}, {{ $r->project->state }} {{ $r->project->pin }}</td></tr>
</table>
<h3>Boundaries</h3>
<table>
    <tr><th>East</th><th>West</th><th>North</th><th>South</th></tr>
    <tr><td>{{ $r->plot->east_boundary ?: '—' }}</td><td>{{ $r->plot->west_boundary ?: '—' }}</td><td>{{ $r->plot->north_boundary ?: '—' }}</td><td>{{ $r->plot->south_boundary ?: '—' }}</td></tr>
</table>
<h2>Parties</h2>
<table>
    <tr><th>Role</th><th>Name</th><th>Relation / age</th><th>Address</th><th>PAN</th></tr>
    @foreach ($r->parties as $p)
        <tr><td>{{ $p->party_type === 'seller' ? 'Owner (seller)' : 'Buyer' }}</td><td class="bold">{{ $p->name }}</td><td>{{ $p->relation_name }}{{ $p->age ? ', '.$p->age.' yrs' : '' }}</td><td>{{ $p->address }}{{ $p->mobile ? ' · '.$p->mobile : '' }}</td><td>{{ $p->panFor($viewer) ?? '—' }}</td></tr>
    @endforeach
</table>
<h2>Witnesses</h2>
<table>
    <tr><th>#</th><th>Name</th><th>Relation / age</th><th>Address</th><th>Signature</th></tr>
    @foreach ($r->witnesses as $i => $w)<tr><td>{{ $i + 1 }}</td><td>{{ $w->name }}</td><td>{{ $w->relation_name }}{{ $w->age ? ', '.$w->age.' yrs' : '' }}</td><td>{{ $w->address }}</td><td style="height:28px"></td></tr>@endforeach
</table>
<h2>Checklist</h2>
<table>
    <tr><th>Doc</th><th>Item</th><th>Mandatory</th><th>Ready</th></tr>
    @foreach ($r->checklist ?? [] as $c)<tr><td>{{ $c['code'] }}</td><td>{{ $c['name'] }}</td><td>{{ $c['mandatory'] ? 'Yes' : 'No' }}</td><td>{{ $c['done'] ? '✔' : '☐' }}</td></tr>@endforeach
</table>
<table class="sign"><tr><td class="center" style="width:33%">Seller</td><td class="center" style="width:33%">Buyer</td><td class="center">Verified by</td></tr></table>
@if ($disclaimer)<div class="disclaimer"><strong>Registration terms:</strong> {{ $disclaimer }}</div>@endif
@endsection
