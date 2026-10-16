{{-- Price display (spec 5.4 / 17.2): actual struck through + offer in teal while the offer is valid. Var: $item (Listing|Plot), optional $big --}}
@php($big = $big ?? false)
@php($suffix = $item instanceof \App\Models\Listing ? $item->priceSuffix() : '')
@if ($item->offerIsActive())
    <span class="whitespace-nowrap">
        <del class="text-muted {{ $big ? 'text-lg' : 'text-sm' }}">@inr($item->actualPrice())</del>
        <strong class="text-teal-700 {{ $big ? 'text-3xl' : 'text-base' }}">@inr($item->currentPrice())</strong>{{ $suffix }}
    </span>
@else
    <strong class="whitespace-nowrap {{ $big ? 'text-3xl' : 'text-base' }}">@inr($item->actualPrice())</strong>{{ $suffix }}
@endif
