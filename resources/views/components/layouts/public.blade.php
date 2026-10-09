@props(['title' => null, 'seo' => [], 'jsonld' => []])
@php
    $customer = auth('customer')->user();
    $staff = auth('web')->user();
    $nav = [['Projects', route('market.projects'), 'market.projects*|market.project*|market.plot'], ['Find a plot', route('market.requirement'), 'market.requirement'], ['Services', route('market.services'), 'market.service*'], ['For promoters', route('market.pricing'), 'market.pricing'], ['Support', route('market.support'), 'market.support']];
    $org = fn ($k) => \App\Services\AppSettings::get($k);
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    @php($title = \App\Services\SeoService::title($seo['title'] ?? $title ?? 'Approved residential plots'))
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — vector7</title>
    <link rel="preload" href="{{ asset('fonts/plus-jakarta-sans-latin-800-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/plus-jakarta-sans-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/brand/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0B1B33">
    @isset($head){{ $head }}@endisset
    @include('partials.seo-head', ['seo' => array_merge($seo, ['title' => $title]), 'jsonld' => $jsonld])
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body class="flex min-h-screen flex-col bg-page">
<a href="#main" class="skip-link">Skip to content</a>
<header class="sticky top-0 z-40 border-b border-navy-50 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3 sm:px-6">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="vector7 home">
            <span class="hidden sm:block"><x-logo class="h-11 w-auto" /></span>
            <span class="sm:hidden"><x-logo variant="compact" class="h-8 w-auto" /></span>
        </a>
        <nav class="ml-4 hidden flex-1 items-center gap-1 lg:flex" aria-label="Main">
            @foreach ($nav as [$label, $url, $pattern])
                @php($on = request()->routeIs(...explode('|', $pattern)))
                <a href="{{ $url }}" class="rounded-lg px-3 py-2 text-[15px] font-medium no-underline {{ $on ? 'text-navy bg-page' : 'text-navy/80 hover:text-navy hover:bg-page' }}" @if ($on) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ml-auto flex items-center gap-2">
            @if ($customer)
                <a href="{{ route('account.dashboard') }}" class="btn-primary btn-pill hidden sm:inline-flex">My account</a>
            @else
                <a href="{{ route('login') }}" class="btn-primary btn-pill hidden sm:inline-flex">Sign in</a>
            @endif
            <a href="{{ $staff ? $staff->homeRoute() : route('staff.login') }}" class="btn-light btn-pill hidden md:inline-flex">Promoter workspace</a>
            <button type="button" class="rounded-lg p-2 lg:hidden" data-nav-toggle aria-controls="public-nav" aria-expanded="false" aria-label="Menu"><x-icon name="menu" class="h-6 w-6" /></button>
        </div>
    </div>
    <nav id="public-nav" class="hidden border-t border-navy-50 px-4 pb-4 lg:hidden" aria-label="Mobile">
        @foreach ($nav as [$label, $url])<a href="{{ $url }}" class="block rounded-lg px-3 py-3 font-medium text-navy no-underline hover:bg-page">{{ $label }}</a>@endforeach
        <div class="mt-2 grid grid-cols-2 gap-2">
            <a href="{{ $customer ? route('account.dashboard') : route('login') }}" class="btn-primary btn-pill">{{ $customer ? 'My account' : 'Sign in' }}</a>
            <a href="{{ $staff ? $staff->homeRoute() : route('staff.login') }}" class="btn-light btn-pill">Promoter workspace</a>
        </div>
    </nav>
</header>

<main id="main" class="flex-1">
    @if (session()->hasAny(['ok', 'error', 'warn']) || $errors->any())<div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6"><x-flash /></div>@endif
    {{ $slot }}
</main>

<footer class="mt-16 bg-navy text-navy-100">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-4">
        <div class="md:col-span-2">
            <x-logo variant="dark" class="h-14 w-auto" />
            <p class="mt-4 max-w-md text-sm leading-relaxed">Approved residential plots from verified layout promoters. See what is available today, check the price and book online — free for buyers.</p>
            <address class="mt-4 text-sm not-italic leading-relaxed">
                {{ $org('org.name') }}<br>{{ $org('org.address') }}<br>
                @if ($org('org.contact')){{ $org('org.contact') }} · @endif<a href="mailto:{{ $org('org.support_email') }}" class="text-teal-200 hover:text-white">{{ $org('org.support_email') }}</a>
            </address>
            <div class="mt-4 flex gap-2">
                @foreach (['instagram', 'facebook', 'youtube', 'linkedin'] as $s)
                    @if ($org('org.'.$s))<a href="{{ $org('org.'.$s) }}" class="rounded-lg bg-white/10 p-2 text-white hover:bg-white/20" rel="noopener" target="_blank" aria-label="{{ ucfirst($s) }}"><x-icon :name="$s" class="h-5 w-5" /></a>@endif
                @endforeach
            </div>
        </div>
        <div>
            <p class="font-bold text-white">Buy a plot</p>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('market.projects') }}" class="text-navy-100 hover:text-white">All projects</a></li>
                <li><a href="{{ route('market.requirement') }}" class="text-navy-100 hover:text-white">Find a plot for my needs</a></li>
                <li><a href="{{ route('market.services') }}" class="text-navy-100 hover:text-white">Legal, survey & loan help</a></li>
                <li><a href="{{ route('register') }}" class="text-navy-100 hover:text-white">Create a free account</a></li>
            </ul>
        </div>
        <div>
            <p class="font-bold text-white">vector7</p>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('market.pricing') }}" class="text-navy-100 hover:text-white">For promoters</a></li>
                <li><a href="{{ route('market.about') }}" class="text-navy-100 hover:text-white">About</a></li>
                <li><a href="{{ route('market.support') }}" class="text-navy-100 hover:text-white">Support</a></li>
                <li><a href="{{ route('market.privacy') }}" class="text-navy-100 hover:text-white">Privacy</a></li>
                <li><a href="{{ route('market.terms') }}" class="text-navy-100 hover:text-white">Terms</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10"><p class="mx-auto max-w-7xl px-4 py-5 text-xs sm:px-6">© {{ date('Y') }} {{ $org('org.name') }}. Prices and availability are set by each promoter and may change.</p></div>
</footer>
</body>
</html>
