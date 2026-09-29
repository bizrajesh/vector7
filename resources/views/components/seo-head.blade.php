@props(['seo'])
{{-- Shared SEO head (SEO standard §4). Every public page must pass a Seo object. --}}
<title>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}">
<link rel="canonical" href="{{ $seo->canonical }}">
<meta name="robots" content="{{ $seo->robots }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:image" content="{{ $seo->ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="en_IN">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo->title }}">
<meta name="twitter:description" content="{{ $seo->description }}">
<meta name="twitter:image" content="{{ $seo->ogImage }}">
@foreach ($seo->jsonLd() as $json)
<script type="application/ld+json">{!! $json !!}</script>
@endforeach
