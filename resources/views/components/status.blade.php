@props(['status', 'label' => null])
<span {{ $attributes->merge(['class' => 'badge st-'.$status]) }}><span class="dot bg-current opacity-70" aria-hidden="true"></span>{{ $label ?? (\App\Models\Plot::STATUSES[$status] ?? ucwords(str_replace('_', ' ', $status))) }}</span>
