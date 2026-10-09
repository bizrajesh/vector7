{{-- Marketplace plot card with tooltip data and filter data-attributes --}}
<article class="plot-card reveal" data-card data-no="{{ $plot->plot_no }}" data-size="{{ (float) $plot->size_sqft }}" data-price="{{ $plot->currentPrice() }}" data-facing="{{ $plot->facing }}" data-corner="{{ $plot->is_corner ? 1 : 0 }}" data-offer="{{ $plot->offerIsActive() ? 1 : 0 }}" data-status="{{ $plot->status }}">
    @if ($plot->offerIsActive())<span class="ribbon">OFFER</span>@endif
    <div class="flex items-start justify-between gap-2" data-plot='@json($plot->tooltipData())' tabindex="0" role="button" aria-label="Plot {{ $plot->plot_no }} details">
        <div>
            <p class="text-xs font-semibold text-muted">Plot</p>
            <p class="text-4xl font-extrabold leading-none tracking-tight text-navy">{{ $plot->plot_no }}</p>
        </div>
        <x-status :status="$plot->status" />
    </div>
    <p class="mt-3 text-sm text-navy"><strong>{{ \App\Support\Format::num($plot->size_sqft) }}</strong> sq ft · {{ $plot->cents() }} cents</p>
    <p class="text-sm text-muted">{{ $plot->facing }} facing @if ($plot->dimensions())· {{ $plot->dimensions() }}@endif @if ($plot->is_corner)· Corner @endif</p>
    <div class="mt-3">@include('partials.plot-price', ['plot' => $plot])</div>
    <div class="mt-4 flex gap-2">
        <a href="{{ $plot->publicUrl() }}" class="btn-light btn-sm flex-1">Details</a>
        @if ($plot->status === 'available')<a href="{{ route('account.book', $plot) }}" class="btn-primary btn-sm flex-1">Book now</a>@endif
    </div>
</article>
