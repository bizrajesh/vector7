@props(['id', 'config', 'label', 'height' => 'h-64'])
<div class="{{ $height }}"><canvas data-chart="{{ $id }}" role="img" aria-label="{{ $label }}"></canvas></div>
<script type="application/json" id="{{ $id }}" nonce="{{ $cspNonce ?? '' }}">{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
