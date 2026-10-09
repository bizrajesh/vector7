@props(['title' => null])
@php
    $u = auth('web')->user();
    $menu = $u->isAppUser() ? \App\Support\Menu::app($u) : \App\Support\Menu::tenant($u);
    $alerts = \App\Services\WorkspaceAlerts::for($u);
    $banners = array_filter($alerts, fn ($a) => $a['banner']);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="min-h-screen">
<a href="#main" class="skip-link">Skip to content</a>
<div class="lg:flex">
    {{-- Sidebar --}}
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-navy/50 lg:hidden" data-sidebar-close></div>
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-navy text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" aria-label="Main navigation">
        <div class="flex items-center justify-between px-5 py-5">
            <a href="{{ $u->homeRoute() }}" class="no-underline" aria-label="vector7 workspace home"><x-logo variant="dark" class="h-11 w-auto" /></a>
            <button type="button" class="rounded-lg p-2 text-white hover:bg-white/10 lg:hidden" data-sidebar-close aria-label="Close menu"><x-icon name="x" /></button>
        </div>
        <div class="px-5 pb-3">
            <p class="truncate text-sm font-bold">{{ $u->isAppUser() ? 'App workspace' : $u->tenant->name }}</p>
            <p class="text-xs text-teal-200">{{ $u->isAppUser() ? 'Platform administration' : 'Tenant ID '.$u->tenant->code }}</p>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 pb-6">
            @foreach ($menu as $group => $items)
                <p class="nav-group">{{ $group }}</p>
                @foreach ($items as $item)
                    @php($active = request()->routeIs($item['route']) || (str_ends_with($item['route'], '.index') && request()->routeIs(\Illuminate\Support\Str::beforeLast($item['route'], '.').'.*')))
                    <a href="{{ route($item['route']) }}" class="nav-link {{ $active ? 'active' : '' }}" @if ($active) aria-current="page" @endif><x-icon :name="$item['icon']" />{{ $item['label'] }}</a>
                @endforeach
            @endforeach
        </nav>
        <div class="border-t border-white/10 px-5 py-4 text-xs text-navy-100">vector7 · Realty Manage Portal</div>
    </aside>

    <div class="min-w-0 flex-1">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-navy-50 bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
            <button type="button" class="rounded-lg p-2 hover:bg-page lg:hidden" data-sidebar-open aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><x-icon name="menu" /></button>
            <a href="{{ $u->homeRoute() }}" class="lg:hidden" aria-label="Home"><x-logo variant="compact" class="h-7 w-auto" /></a>
            <div class="hidden min-w-0 flex-1 lg:block">
                <p class="truncate text-sm font-bold text-navy">{{ $u->isAppUser() ? 'vector7 App workspace' : $u->tenant->name }}</p>
            </div>
            <div class="ml-auto flex items-center gap-1">
                @unless ($u->isAppUser())
                    <a href="{{ route('home') }}" class="btn-ghost btn-sm hidden sm:inline-flex" target="_blank" rel="noopener">{!! \App\Support\Icons::svg('globe', 'h-4 w-4') !!} Marketplace</a>
                @endunless
                <details class="relative" data-dropdown>
                    <summary class="relative list-none cursor-pointer rounded-lg p-2 hover:bg-page" aria-label="Notifications ({{ count($alerts) }})">
                        <x-icon name="bell" />
                        @if (count($alerts))<span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ count($alerts) }}</span>@endif
                    </summary>
                    <div class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl bg-white p-2 shadow-lift ring-1 ring-navy-50">
                        <p class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-muted">Notifications</p>
                        @forelse ($alerts as $a)
                            <a href="{{ $a['link'] ?? '#' }}" class="flex gap-2 rounded-lg px-3 py-2 text-sm text-navy no-underline hover:bg-page">
                                <span class="mt-1.5 dot {{ $a['level'] === 'red' ? 'bg-red-600' : ($a['level'] === 'amber' ? 'bg-amber-500' : 'bg-teal') }}"></span><span>{{ $a['text'] }}</span>
                            </a>
                        @empty
                            <p class="px-3 py-4 text-sm text-muted">You're all caught up.</p>
                        @endforelse
                    </div>
                </details>
                <details class="relative" data-dropdown>
                    <summary class="flex list-none cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-page">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-navy">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        <span class="hidden text-left sm:block"><span class="block text-sm font-semibold leading-4">{{ $u->name }}</span><span class="block text-xs text-muted">{{ $u->role->name }}</span></span>
                        <x-icon name="chevron-down" class="h-4 w-4 text-muted" />
                    </summary>
                    <div class="absolute right-0 z-30 mt-2 w-56 rounded-xl bg-white p-2 shadow-lift ring-1 ring-navy-50">
                        <a href="{{ route('profile') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-navy no-underline hover:bg-page"><x-icon name="user" class="h-4 w-4" /> My profile</a>
                        <form method="POST" action="{{ route('staff.logout') }}">@csrf
                            <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-navy hover:bg-page"><x-icon name="logout" class="h-4 w-4" /> Sign out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        @foreach ($banners as $b)
            <div class="{{ $b['level'] === 'red' ? 'bg-red-700' : 'bg-amber-100' }} {{ $b['level'] === 'red' ? 'text-white' : 'text-amber-950' }} px-4 py-2 text-sm font-medium sm:px-6" role="status">
                {{ $b['text'] }} @if ($b['link'])<a href="{{ $b['link'] }}" class="ml-1 font-bold underline {{ $b['level'] === 'red' ? 'text-white' : 'text-amber-950' }}">Upgrade / renew</a>@endif
            </div>
        @endforeach

        <main id="main" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
            <div class="mb-4"><x-flash /></div>
            {{ $slot }}
        </main>
    </div>
</div>
<div id="v7-confirm" class="fixed inset-0 z-[70] hidden items-center justify-center bg-navy/50 p-4" role="dialog" aria-modal="true" aria-labelledby="v7-confirm-text">
    <div class="w-full max-w-sm rounded-card bg-white p-6 shadow-lift">
        <p id="v7-confirm-text" class="font-semibold text-navy"></p>
        <div class="mt-3 hidden" data-confirm-password-box>
            <label for="v7-confirm-password" class="label">Confirm with your password</label>
            <input id="v7-confirm-password" type="password" class="input" autocomplete="current-password">
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="btn-light" data-confirm-cancel>Cancel</button>
            <button type="button" class="btn-primary" data-confirm-ok>Confirm</button>
        </div>
    </div>
</div>
</body>
</html>
