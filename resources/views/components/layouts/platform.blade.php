@props(['title' => 'Platform'])
@php
    $nav = [
        ['Overview', 'chart', route('platform.dashboard'), request()->routeIs('platform.dashboard')],
        ['Tenants', 'building', route('platform.tenants.index'), request()->routeIs('platform.tenants.*')],
        ['Subscription plans', 'tag', route('platform.plans.index'), request()->routeIs('platform.plans.*')],
    ];
@endphp
<!doctype html>
<html lang="en-IN">
<head>
    <x-head-common :charts="true" />
    <title>{{ $title }} · Vector7 Platform</title>
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-[#F4F5F7]">
<div id="drawer-backdrop" data-close-drawer class="fixed inset-0 z-30 hidden bg-ink/40 lg:hidden"></div>
<aside id="drawer" class="fixed inset-y-0 left-0 z-40 flex w-[248px] -translate-x-full flex-col gap-6 bg-navy-900 px-4 py-5 transition-transform lg:translate-x-0" aria-label="Platform navigation">
    <div class="flex items-center gap-2.5 px-2">
        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-sage text-[13px] font-extrabold tracking-tight text-navy">V7</span>
        <span class="flex flex-col"><span class="text-[19px] font-bold text-white">Vector7</span><span class="text-[11px] font-semibold tracking-wider text-cream">PLATFORM CONSOLE</span></span>
    </div>
    <nav class="flex flex-col gap-0.5">
        @foreach ($nav as [$label, $icon, $url, $active])
            <a href="{{ $url }}" class="nav-link {{ $active ? 'active' : '' }}"><x-icon :name="$icon" class="h-[18px] w-[18px]" />{{ $label }}</a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('logout') }}" class="mt-auto">@csrf
        <button class="nav-link w-full"><x-icon name="logout" class="h-[18px] w-[18px]" />Sign out</button>
    </form>
</aside>
<div class="lg:pl-[248px]">
    <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-line bg-white px-3 lg:hidden">
        <button type="button" data-open-drawer class="flex h-11 w-11 items-center justify-center rounded-xl" aria-label="Open menu"><x-icon name="menu" /></button>
        <span class="font-bold">{{ $title }}</span>
    </header>
    <main class="mx-auto max-w-[1400px] px-4 py-6 sm:px-8">
        <x-flash />
        {{ $slot }}
    </main>
</div>
</body>
</html>
