@props(['title' => null, 'seo' => [], 'jsonld' => []])
@php
    $customer = auth('customer')->user();
    $staff = auth('web')->user();
    $staff = $staff && ! $staff->isAppUser() ? $staff : null; // the marketplace never links to the App workspace
    $PS = \App\Services\PropertySearch::class;
    $buy = [[__('Plots & layouts'), $PS::categoryUrl('plot')], [__('Flats'), $PS::categoryUrl('flat')], [__('Villas'), $PS::categoryUrl('villa')],
        [__('Independent houses'), $PS::categoryUrl('house')], [__('Farm land'), $PS::categoryUrl('farm_land')], [__('Commercial'), $PS::categoryUrl('commercial')]];
    $rentMenu = [[__('Flats'), $PS::categoryUrl('flat', 'rent')], [__('Independent houses'), $PS::categoryUrl('house', 'rent')], [__('Villas'), $PS::categoryUrl('villa', 'rent')],
        [__('Shops & offices'), $PS::categoryUrl('commercial', 'rent')]];
    $routePurpose = request()->routeIs('market.category', 'market.listing') ? request()->route('purpose') : null;
    $routeType = request()->routeIs('market.category', 'market.listing') ? request()->route('type') : null;
    $nav = [
        [__('Rent'), route('market.category', ['purpose' => 'rent']), $routePurpose === 'rent'],
        [__('Layouts'), $PS::categoryUrl('layout'), $routeType === \App\Listings\ListingTypes::LAYOUT_SLUG || request()->routeIs('market.projects*', 'market.project*', 'market.plot')],
        [__('Services'), route('market.services'), request()->routeIs('market.service*')],
        [__('For promoters'), route('market.pricing'), request()->routeIs('market.pricing')],
        [__('Support'), route('market.support'), request()->routeIs('market.support')],
    ];
    $buyOn = $routePurpose === 'buy' && $routeType !== \App\Listings\ListingTypes::LAYOUT_SLUG;
    $locale = app()->getLocale();
    $postUrl = $staff && $staff->tenant_id ? route('ws.listings.create') : route('lister.register');
    $org = fn ($k) => \App\Services\AppSettings::get($k);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale === 'ta' ? 'ta-IN' : 'en-IN' }}">
<head>
    @php($title = \App\Services\SeoService::title($seo['title'] ?? $title ?? __('Approved residential plots')))
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
    @foreach (\App\Http\Middleware\SetMarketLocale::LOCALES as $code => $name)
        <link rel="alternate" hreflang="{{ $code }}-IN" href="{{ \App\Http\Middleware\SetMarketLocale::url($code) }}">
    @endforeach
    <script type="application/json" id="v7-i18n" nonce="{{ $cspNonce ?? '' }}">{!! json_encode(\App\Support\JsStrings::all(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body class="flex min-h-screen flex-col bg-page {{ $locale === 'ta' ? 'lang-ta' : '' }}">
<a href="#main" class="skip-link">{{ __('Skip to content') }}</a>
<header class="sticky top-0 z-40 border-b border-navy-50 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3 sm:px-6">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ __('vector7 home') }}">
            <span class="hidden sm:block"><x-logo class="h-11 w-auto" /></span>
            <span class="sm:hidden"><x-logo variant="compact" class="h-8 w-auto" /></span>
        </a>
        <nav class="ml-2 hidden flex-1 items-center gap-1 lg:flex" aria-label="{{ __('Main') }}">
            <details class="relative" data-dropdown>
                <summary class="flex cursor-pointer list-none items-center gap-1 whitespace-nowrap rounded-lg py-2 font-medium {{ $locale === 'ta' ? 'px-2 text-sm' : 'px-3 text-[15px]' }} {{ $buyOn ? 'bg-page text-navy' : 'text-navy/80 hover:bg-page hover:text-navy' }}">{{ __('Buy') }} <x-icon name="chevron-down" class="h-4 w-4" /></summary>
                <ul class="absolute left-0 top-full z-50 mt-1 w-56 rounded-xl bg-white p-2 shadow-lift ring-1 ring-navy-50">
                    @foreach ($buy as [$label, $url])<li><a href="{{ $url }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-navy no-underline hover:bg-page">{{ $label }}</a></li>@endforeach
                    <li class="mt-1 border-t border-navy-50 pt-1"><a href="{{ route('market.category', ['purpose' => 'buy']) }}" class="block rounded-lg px-3 py-2 text-sm font-semibold no-underline hover:bg-page">{{ __('All properties for sale') }}</a></li>
                </ul>
            </details>
            @foreach ($nav as [$label, $url, $on])
                <a href="{{ $url }}" class="whitespace-nowrap rounded-lg py-2 font-medium no-underline {{ $locale === 'ta' ? 'px-2 text-sm' : 'px-3 text-[15px]' }} {{ $on ? 'text-navy bg-page' : 'text-navy/80 hover:text-navy hover:bg-page' }}" @if ($on) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <div class="flex rounded-full bg-page p-0.5 text-sm" role="group" aria-label="{{ __('Language') }}">
                @foreach (\App\Http\Middleware\SetMarketLocale::LOCALES as $code => $name)
                    <a href="{{ \App\Http\Middleware\SetMarketLocale::url($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" class="rounded-full px-2.5 py-1 font-semibold no-underline {{ $locale === $code ? 'bg-navy text-white' : 'text-navy' }}" @if ($locale === $code) aria-current="true" @endif>{{ $code === 'en' ? 'EN' : 'தமிழ்' }}</a>
                @endforeach
            </div>
            @if ($customer)
                <a href="{{ route('account.dashboard') }}" class="btn-primary btn-pill hidden sm:inline-flex">{{ __('My account') }}</a>
            @elseif ($staff)
                <a href="{{ $staff->homeRoute() }}" class="btn-primary btn-pill hidden sm:inline-flex">{{ $staff->isLister() ? __('My listings') : __('Workspace') }}</a>
            @else
                <details class="relative hidden sm:block" data-dropdown>
                    <summary class="btn-primary btn-pill list-none cursor-pointer">{{ __('Sign in') }} <x-icon name="chevron-down" class="h-4 w-4" /></summary>
                    <ul class="absolute right-0 top-full z-50 mt-1 w-64 rounded-xl bg-white p-2 shadow-lift ring-1 ring-navy-50">
                        <li><a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 text-sm no-underline hover:bg-page"><span class="font-semibold text-navy">{{ __('Buyer') }}</span><span class="block text-xs text-muted">{{ __('Shortlist, bookings, payments') }}</span></a></li>
                        <li><a href="{{ route('lister.login') }}" class="block rounded-lg px-3 py-2 text-sm no-underline hover:bg-page"><span class="font-semibold text-navy">{{ __('Property lister') }}</span><span class="block text-xs text-muted">{{ __('Post and track your listings') }}</span></a></li>
                        <li><a href="{{ route('staff.login') }}" class="block rounded-lg px-3 py-2 text-sm no-underline hover:bg-page"><span class="font-semibold text-navy">{{ __('Layout promoter') }}</span><span class="block text-xs text-muted">{{ __('Promoter workspace') }}</span></a></li>
                    </ul>
                </details>
            @endif
            <a href="{{ $postUrl }}" class="btn btn-pill hidden whitespace-nowrap bg-gold text-navy hover:bg-amber-300 {{ $locale === 'ta' ? 'xl:inline-flex' : 'md:inline-flex' }}"><x-icon name="plus" class="h-4 w-4" /> {{ __('Post property') }}</a>
            <a href="{{ route('market.search') }}" class="rounded-lg p-2 text-navy lg:hidden" aria-label="{{ __('Search') }}"><x-icon name="search" class="h-6 w-6" /></a>
            <button type="button" class="rounded-lg p-2 lg:hidden" data-nav-toggle aria-controls="public-nav" aria-expanded="false" aria-label="{{ __('Menu') }}"><x-icon name="menu" class="h-6 w-6" /></button>
        </div>
    </div>
    <nav id="public-nav" class="hidden border-t border-navy-50 px-4 pb-4 lg:hidden" aria-label="{{ __('Mobile') }}">
        <p class="px-3 pt-3 text-xs font-bold uppercase tracking-wide text-muted">{{ __('Buy') }}</p>
        @foreach ($buy as [$label, $url])<a href="{{ $url }}" class="block rounded-lg px-3 py-2.5 font-medium text-navy no-underline hover:bg-page">{{ $label }}</a>@endforeach
        <div class="my-2 border-t border-navy-50"></div>
        @foreach ($nav as [$label, $url, $on])<a href="{{ $url }}" class="block rounded-lg px-3 py-3 font-medium text-navy no-underline hover:bg-page">{{ $label }}</a>@endforeach
        <div class="mt-2 grid grid-cols-2 gap-2">
            <a href="{{ $customer ? route('account.dashboard') : route('login') }}" class="btn-primary btn-pill">{{ $customer ? __('My account') : __('Buyer sign in') }}</a>
            <a href="{{ $postUrl }}" class="btn btn-pill bg-gold text-navy">{{ __('Post property') }}</a>
            <a href="{{ $staff && $staff->isLister() ? $staff->homeRoute() : route('lister.login') }}" class="btn-light btn-pill">{{ __('Property lister') }}</a>
            <a href="{{ $staff && ! $staff->isLister() ? $staff->homeRoute() : route('staff.login') }}" class="btn-light btn-pill">{{ __('Promoter workspace') }}</a>
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
            <p class="mt-4 max-w-md text-sm leading-relaxed">{{ __('Plots, flats, villas, houses, farm land and commercial space from verified promoters and owners in Tamil Nadu. Real photos, today\'s price and a direct line to the owner — free for buyers.') }}</p>
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
            <p class="font-bold text-white">{{ __('Find a property') }}</p>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($buy as [$label, $url])<li><a href="{{ $url }}" class="text-navy-100 hover:text-white">{{ __(':what for sale', ['what' => $label]) }}</a></li>@endforeach
                @foreach ($rentMenu as [$label, $url])<li><a href="{{ $url }}" class="text-navy-100 hover:text-white">{{ __(':what for rent', ['what' => $label]) }}</a></li>@endforeach
                <li><a href="{{ route('market.category', ['purpose' => 'rent']) }}" class="text-navy-100 hover:text-white">{{ __('Properties for rent') }}</a></li>
                <li><a href="{{ route('market.requirement') }}" class="text-navy-100 hover:text-white">{{ __('Find a plot for my needs') }}</a></li>
                <li><a href="{{ route('market.services') }}" class="text-navy-100 hover:text-white">{{ __('Legal, survey & loan help') }}</a></li>
                <li><a href="{{ route('register') }}" class="text-navy-100 hover:text-white">{{ __('Create a free account') }}</a></li>
            </ul>
        </div>
        <div>
            <p class="font-bold text-white">{{ __('vector7') }}</p>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="{{ route('market.pricing') }}" class="text-navy-100 hover:text-white">{{ __('For promoters') }}</a></li>
                <li><a href="{{ route('lister.register') }}" class="text-navy-100 hover:text-white">{{ __('List your property') }}</a></li>
                <li><a href="{{ route('lister.login') }}" class="text-navy-100 hover:text-white">{{ __('Property lister sign in') }}</a></li>
                <li><a href="{{ $staff && ! $staff->isLister() ? $staff->homeRoute() : route('staff.login') }}" class="text-navy-100 hover:text-white">{{ __('Promoter workspace') }}</a></li>
                <li><a href="{{ route('market.about') }}" class="text-navy-100 hover:text-white">{{ __('About') }}</a></li>
                <li><a href="{{ route('market.support') }}" class="text-navy-100 hover:text-white">{{ __('Support') }}</a></li>
                <li><a href="{{ route('market.privacy') }}" class="text-navy-100 hover:text-white">{{ __('Privacy') }}</a></li>
                <li><a href="{{ route('market.terms') }}" class="text-navy-100 hover:text-white">{{ __('Terms') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10"><p class="mx-auto max-w-7xl px-4 py-5 text-xs sm:px-6">© {{ date('Y') }} {{ $org('org.name') }}. {{ __('Prices and availability are set by each promoter and may change.') }}</p></div>
</footer>
@if (\App\Services\AppSettings::get('ai.market_chat_enabled', '1') === '1')
    @include('partials.chat-widget', ['bot' => 'market', 'historyUrl' => route('market.chat'), 'sendUrl' => route('market.chat.send'), 'consentUrl' => route('market.chat.consent'), 'title' => \App\Services\AppSettings::get('ai.bot_name', 'Vee').' · vector7 assistant'])
@endif
</body>
</html>
