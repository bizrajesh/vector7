{{-- Actual / offer price display. $plot, $size = 'lg'|'sm' --}}
@php($big = ($size ?? 'sm') === 'lg')
@if ($plot->offerIsActive())
    <div>
        <p class="text-sm text-muted"><span class="line-through">@inr($plot->actualPrice())</span> <span class="ml-1 font-semibold text-teal-700">Save @inr($plot->savingAmount())</span></p>
        <p class="{{ $big ? 'text-4xl' : 'text-2xl' }} font-extrabold tracking-tight text-teal-700">@inr($plot->offerPrice())</p>
        <p class="text-xs text-muted">@inr($plot->offer_rate_per_sqft) / sq ft · {{ $plot->offer_text ?: 'Offer price' }} · @php($d = $plot->offerDaysLeft()){{ $d === 0 ? 'Offer ends today' : 'Offer ends in '.$d.' day'.($d > 1 ? 's' : '') }} ({{ $plot->offer_valid_till->format('d-m-Y') }})</p>
    </div>
@else
    <div>
        <p class="{{ $big ? 'text-4xl' : 'text-2xl' }} font-extrabold tracking-tight text-navy">@inr($plot->actualPrice())</p>
        <p class="text-xs text-muted">@inr($plot->rate_per_sqft) / sq ft</p>
    </div>
@endif
