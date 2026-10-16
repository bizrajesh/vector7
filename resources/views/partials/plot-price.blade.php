{{-- Actual / offer price display. $plot, $size = 'lg'|'sm' --}}
@php($big = ($size ?? 'sm') === 'lg')
@if ($plot->offerIsActive())
    <div>
        <p class="text-sm text-muted"><span class="line-through">@inr($plot->actualPrice())</span> <span class="ml-1 font-semibold text-teal-700">{{ __('Save :amount', ['amount' => \App\Support\Format::inr($plot->savingAmount())]) }}</span></p>
        <p class="{{ $big ? 'text-4xl' : 'text-2xl' }} font-extrabold tracking-tight text-teal-700">@inr($plot->offerPrice())</p>
        <p class="text-xs text-muted">@inr($plot->offer_rate_per_sqft) / {{ __('sq ft') }} · {{ $plot->offer_text ?: __('Offer price') }} · @php($d = $plot->offerDaysLeft()){{ $d === 0 ? __('Offer ends today') : trans_choice('Offer ends in :count day|Offer ends in :count days', $d) }} ({{ $plot->offer_valid_till->format('d-m-Y') }})</p>
    </div>
@else
    <div>
        <p class="{{ $big ? 'text-4xl' : 'text-2xl' }} font-extrabold tracking-tight text-navy">@inr($plot->actualPrice())</p>
        <p class="text-xs text-muted">@inr($plot->rate_per_sqft) / {{ __('sq ft') }}</p>
    </div>
@endif
