@extends('pdf.layout', ['docTitle' => 'Customer acknowledgement', 'docSub' => 'Plot '.$r->plot->plot_no.' · '.$r->project->name])
@section('content')
<p style="font-size:12px; line-height:1.7; margin-top:20px">
    I, <strong>{{ $r->customer->name }}</strong>, buyer of Plot No. <strong>{{ $r->plot->plot_no }}</strong> ({{ \App\Support\Format::num($r->plot->size_sqft) }} sq ft, Patta No. {{ $r->plot->patta_number }})
    in <strong>{{ $r->project->name }}</strong>, {{ $r->project->location }}, {{ $r->project->district }}, confirm that the sale deed was registered at
    {{ $r->sro?->name }} as Document No. <strong>{{ $r->registered_doc_no }}</strong> dated <strong>{{ $r->registered_doc_date?->format('d-m-Y') }}</strong>.
</p>
<p style="font-size:12px; line-height:1.7">
    I have received all the documents relating to the above plot from <strong>{{ $tenant->name }}</strong>, and I confirm that there are <strong>no dues</strong> payable by me
    towards this plot. The total sale value of {{ \App\Support\Format::inr($r->sale->net_price, 2) }} has been paid in full.
</p>
<table class="sign"><tr><td style="width:50%">Place: {{ $r->project->location }}<br>Date: ________________</td><td class="center">{{ $r->customer->name }}<br>(Buyer signature)</td></tr></table>
@endsection
