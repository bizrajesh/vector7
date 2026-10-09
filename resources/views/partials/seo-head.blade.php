{{-- $seo = ['title','description','canonical','image','type','noindex'], $jsonld = [array, ...] --}}
@php
    $seo = $seo ?? [];
    $t = \App\Services\SeoService::title($seo['title'] ?? \App\Services\AppSettings::get('seo.default_title'));
    $d = \App\Services\SeoService::description($seo['description'] ?? \App\Services\AppSettings::get('seo.default_description'));
    $canonical = $seo['canonical'] ?? url()->current();
    $img = $seo['image'] ?? asset('images/brand/logo-light.png');
    $noindex = ($seo['noindex'] ?? false) || ! config('seo.indexable');
@endphp
<meta name="description" content="{{ $d }}">
<link rel="canonical" href="{{ $canonical }}">
@if ($noindex)<meta name="robots" content="noindex, nofollow">@else<meta name="robots" content="index, follow, max-image-preview:large">@endif
<meta property="og:site_name" content="vector7">
<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
<meta property="og:title" content="{{ $t }}">
<meta property="og:description" content="{{ $d }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $img }}">
<meta property="og:locale" content="en_IN">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $t }}">
<meta name="twitter:description" content="{{ $d }}">
<meta name="twitter:image" content="{{ $img }}">
@foreach (($jsonld ?? []) as $ld)
<script type="application/ld+json" nonce="{{ $cspNonce ?? '' }}">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
