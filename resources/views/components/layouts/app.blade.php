@props(['title' => 'Vector7'])
@php
    $user = auth()->user();
    $tenant = $currentTenant ?? null;
    $sub = $tenant?->subscription;
    $ops = request()->routeIs('app.layouts.index') && request('status') === 'in_progress';
    $newRequests = $user->can('requests.manage') ? \App\Models\PurchaseRequest::query()->where('status', 'new')->count() : 0;
    $nav = [
        ['Dashboard', 'home', route('app.dashboard'), request()->routeIs('app.dashboard'), true],
        ['Operations', 'ops', route('app.layouts.index', ['status' => 'in_progress']), $ops, $user->can('layouts.view')],
        ['Layout Projects', 'map', route('app.layouts.index'), request()->routeIs('app.layouts.*') && ! $ops, $user->can('layouts.view')],
        ['Launch & Sales', 'tag', route('app.sales.index'), request()->routeIs('app.sales.*', 'app.plots.*', 'app.bookings.*', 'app.registrations.*'), $user->can('sales.create')],
        ['Requests'.($newRequests ? ' · '.$newRequests : ''), 'bell', route('app.requests.index'), request()->routeIs('app.requests.*'), $user->can('requests.manage')],
        ['Customers', 'user-plus', route('app.customers.index'), request()->routeIs('app.customers.*'), $user->can('customers.view')],
        ['Manage Shares', 'pie', route('app.shares.index'), request()->routeIs('app.shares.*'), $user->can('shares.view')],
        ['Accounting', 'wallet', route('app.ledger.index'), request()->routeIs('app.ledger.*'), $user->can('ledger.view') || $user->can('ledger.view.sales')],
        ['Business Analytics', 'chart', route('app.analytics'), request()->routeIs('app.analytics'), $user->can('analytics.view') || $user->can('analytics.view.sales')],
        null,
        ['Users & Roles', 'users', route('app.users.index'), request()->routeIs('app.users.*'), $user->can('users.manage')],
        ['Settings', 'sliders', route('app.settings.edit'), request()->routeIs('app.settings.*'), $user->can('settings.manage')],
    ];
@endphp
<!doctype html>
<html lang="en-IN">
<head>
    <x-head-common :charts="true" />
    <title>{{ $title }} · Vector7</title>
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

@if ($impersonating ?? false)
    <div class="no-print flex items-center justify-between gap-3 bg-[#A12622] px-4 py-2 text-sm font-semibold text-white">
        <span>Super Admin impersonation — every action is logged.</span>
        <form method="POST" action="{{ route('impersonation.stop') }}">@csrf<button class="rounded-lg bg-white/15 px-3 py-1 hover:bg-white/25">End session</button></form>
    </div>
@endif

<div id="drawer-backdrop" data-close-drawer class="fixed inset-0 z-30 hidden bg-ink/40 lg:hidden"></div>

<aside id="drawer" class="fixed inset-y-0 left-0 z-40 flex w-[264px] -translate-x-full flex-col gap-6 overflow-y-auto bg-navy px-4 py-5 transition-transform duration-200 lg:w-[248px] lg:translate-x-0" aria-label="Main navigation">
    <div class="flex items-center gap-2.5 px-2">
        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[9px] bg-sage text-[13px] font-extrabold tracking-tight text-navy">V7</span>
        <span class="flex min-w-0 flex-col">
            <span class="text-[19px] font-bold text-white">Vector7</span>
            <span class="truncate text-xs text-navy-200">{{ $tenant?->name }}</span>
        </span>
    </div>
    <nav class="flex flex-col gap-0.5">
        @foreach ($nav as $item)
            @if ($item === null)
                <div class="mx-2 my-2 h-px bg-white/10"></div>
            @elseif ($item[4])
                <a href="{{ $item[2] }}" class="nav-link {{ $item[3] ? 'active' : '' }}" @if ($item[3]) aria-current="page" @endif>
                    <x-icon :name="$item[1]" class="h-[18px] w-[18px]" />{{ $item[0] }}
                </a>
            @endif
        @endforeach
    </nav>
    @if ($user->hasRole('admin') && $tenant?->plan)
        <a href="{{ route('app.billing') }}" class="mt-auto flex flex-col gap-2 rounded-xl bg-white/[0.07] p-3.5 no-underline hover:bg-white/10">
            <span class="flex items-center justify-between"><span class="text-[13px] font-semibold text-white">{{ $tenant->plan->name }} plan</span><span class="text-xs font-semibold text-cream">Billing</span></span>
            <span class="text-xs text-navy-200">{{ $tenant->status->label() }}@if ($sub?->trial_ends_at && $tenant->status->value === 'trial') · ends {{ $sub->trial_ends_at->format('d M') }}@endif</span>
        </a>
    @endif
</aside>

<div class="lg:pl-[248px]">
    <header class="no-print sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white px-3 sm:px-8">
        <button type="button" data-open-drawer class="flex h-11 w-11 items-center justify-center rounded-xl text-ink hover:bg-cream-100 lg:hidden" aria-label="Open menu" aria-controls="drawer"><x-icon name="menu" /></button>
        <form action="{{ route('app.customers.index') }}" method="GET" role="search" class="hidden w-[380px] md:block">
            <label class="flex h-10 items-center gap-2.5 rounded-[10px] border border-line bg-[#FAF9F2] px-3 text-ink-muted">
                <x-icon name="search" class="h-[18px] w-[18px]" />
                <input type="search" name="q" placeholder="Search customer name or phone" aria-label="Search customers" class="w-full border-0 bg-transparent p-0 text-sm text-ink focus:ring-0">
            </label>
        </form>
        <span class="text-[17px] font-bold md:hidden">{{ $title }}</span>
        <div class="ml-auto flex items-center gap-2">
            @can('bookings.create')
                <a href="{{ route('app.layouts.index', ['status' => 'launched']) }}" class="btn-primary btn-sm hidden sm:inline-flex"><x-icon name="plus" class="h-4 w-4" stroke="2.2" />New booking</a>
            @endcan
            <details class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-1.5 py-1 hover:bg-cream-100">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-teal-50 text-[13px] font-bold text-teal">{{ $user->initials() }}</span>
                    <span class="hidden flex-col leading-tight sm:flex"><span class="text-[13.5px] font-semibold">{{ $user->name }}</span><span class="text-xs text-ink-muted">{{ $user->role->label() }}</span></span>
                </summary>
                <div class="absolute right-0 mt-2 w-48 rounded-xl border border-line bg-white p-1.5 shadow-card">
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-cream-100"><x-icon name="logout" class="h-4 w-4" />Sign out</button>
                    </form>
                </div>
            </details>
        </div>
    </header>

    @if ($tenant && in_array($tenant->status->value, ['past_due', 'suspended'], true))
        <div class="no-print border-b border-[#F1D9A6] bg-[#FDF6E7] px-4 py-2.5 text-sm text-[#5E3D00] sm:px-8">
            {{ $tenant->status->value === 'suspended' ? 'Your workspace is read-only until the subscription is renewed.' : 'Your subscription payment is due.' }}
            @if ($user->hasRole('admin'))<a href="{{ route('app.billing') }}" class="font-semibold">Renew now</a>@endif
        </div>
    @endif

    <main id="main" class="mx-auto max-w-[1400px] px-4 pb-28 pt-5 sm:px-8 sm:pt-6 lg:pb-10">
        <x-flash />
        {{ $slot }}
    </main>
</div>

{{-- Phone bottom tab bar (role-based, max 5 items) --}}
<nav class="no-print fixed inset-x-0 bottom-0 z-20 flex h-[72px] border-t border-line bg-white px-2 pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Primary">
    <a href="{{ route('app.dashboard') }}" class="tab-link {{ request()->routeIs('app.dashboard') ? 'active' : '' }}"><x-icon name="home" class="h-[22px] w-[22px]" />Home</a>
    @if ($user->hasRole('sales'))
        <a href="{{ route('app.layouts.index', ['status' => 'launched']) }}" class="tab-link {{ request()->routeIs('app.plots.*') ? 'active' : '' }}"><x-icon name="grid" class="h-[22px] w-[22px]" />Plots</a>
        <a href="{{ route('app.layouts.index', ['status' => 'launched']) }}" class="tab-link"><span class="-mt-2.5 flex h-11 w-11 items-center justify-center rounded-[14px] bg-teal text-white"><x-icon name="plus" class="h-[22px] w-[22px]" stroke="2.2" /></span>Book</a>
        <a href="{{ route('app.sales.index') }}" class="tab-link {{ request()->routeIs('app.sales.*') ? 'active' : '' }}"><x-icon name="wallet" class="h-[22px] w-[22px]" />Payments</a>
    @else
        <a href="{{ route('app.layouts.index') }}" class="tab-link {{ request()->routeIs('app.layouts.*') ? 'active' : '' }}"><x-icon name="map" class="h-[22px] w-[22px]" />Projects</a>
        <a href="{{ route('app.sales.index') }}" class="tab-link {{ request()->routeIs('app.sales.*', 'app.plots.*') ? 'active' : '' }}"><x-icon name="tag" class="h-[22px] w-[22px]" />Sales</a>
        <a href="{{ route('app.shares.index') }}" class="tab-link {{ request()->routeIs('app.shares.*') ? 'active' : '' }}"><x-icon name="pie" class="h-[22px] w-[22px]" />Shares</a>
    @endif
    <button type="button" data-open-drawer class="tab-link" aria-controls="drawer"><x-icon name="dots" class="h-[22px] w-[22px]" />More</button>
</nav>
</body>
</html>
