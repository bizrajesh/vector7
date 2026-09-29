@props(['status'])
{{-- Accepts a PlotStatus / LayoutStatus / SubscriptionStatus enum --}}
<span {{ $attributes->merge(['class' => 'badge-'.$status->css()]) }}>{{ $status->label() }}</span>
