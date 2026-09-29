@props(['charts' => false])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#1C315E">
<link rel="icon" href="{{ asset('img/icon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<link rel="preload" href="{{ asset('fonts/plus-jakarta-sans-600.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
@if ($charts)<script src="{{ asset('js/vendor/chart.umd.min.js') }}" defer></script>@endif
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
