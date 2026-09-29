@props(['seo'])
<!doctype html>
<html lang="en-IN">
<head>
    <x-head-common />
    <x-seo-head :seo="$seo" />
</head>
<body class="bg-ground">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
<header class="border-b border-line bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-ink no-underline hover:text-ink">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-navy text-[12px] font-extrabold tracking-tight text-sage">V7</span>
            <span class="text-lg font-bold">Vector7</span>
        </a>
        <nav class="ml-auto hidden items-center gap-6 text-sm font-semibold md:flex" aria-label="Main">
            <a href="{{ route('projects.index') }}" class="text-ink-2 hover:text-ink">Projects</a>
            <a href="{{ route('track') }}" class="text-ink-2 hover:text-ink">Track booking</a>
            <a href="{{ route('features') }}" class="text-ink-2 hover:text-ink">For developers</a>
            <a href="{{ route('pricing') }}" class="text-ink-2 hover:text-ink">Pricing</a>
            <a href="{{ route('about') }}" class="text-ink-2 hover:text-ink">About</a>
            <a href="{{ route('contact') }}" class="text-ink-2 hover:text-ink">Contact</a>
        </nav>
        <div class="ml-auto flex items-center gap-2 md:ml-0">
            <a href="{{ route('login') }}" class="btn-ghost btn-sm">Sign in</a>
            <a href="{{ route('projects.index') }}" class="btn-primary btn-sm md:hidden">Projects</a>
            <a href="{{ route('register') }}" class="btn-primary btn-sm hidden md:inline-flex">Start free trial</a>
        </div>
    </div>
</header>
<main id="main">{{ $slot }}</main>
<footer class="mt-16 bg-navy text-navy-200">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-3">
        <div>
            <p class="text-lg font-bold text-white">Vector7</p>
            <p class="mt-2 text-sm">Layout projects, plot sales and shareholder value — in one secure workspace.</p>
        </div>
        <nav aria-label="Product" class="flex flex-col gap-2 text-sm">
            <a href="{{ route('projects.index') }}" class="text-navy-200 hover:text-white">Browse projects</a>
            <a href="{{ route('track') }}" class="text-navy-200 hover:text-white">Track my booking</a>
            <a href="{{ route('features') }}" class="text-navy-200 hover:text-white">Features</a>
            <a href="{{ route('pricing') }}" class="text-navy-200 hover:text-white">Pricing</a>
            <a href="{{ route('register') }}" class="text-navy-200 hover:text-white">Start free trial</a>
        </nav>
        <nav aria-label="Company" class="flex flex-col gap-2 text-sm">
            <a href="{{ route('about') }}" class="text-navy-200 hover:text-white">About</a>
            <a href="{{ route('contact') }}" class="text-navy-200 hover:text-white">Contact</a>
            <a href="{{ route('privacy') }}" class="text-navy-200 hover:text-white">Privacy policy</a>
            <a href="{{ route('terms') }}" class="text-navy-200 hover:text-white">Terms of service</a>
        </nav>
    </div>
    <p class="border-t border-white/10 py-4 text-center text-xs">© {{ date('Y') }} Vector7. All rights reserved.</p>
</footer>
</body>
</html>
