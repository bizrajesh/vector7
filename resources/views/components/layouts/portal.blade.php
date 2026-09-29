@props(['title', 'tabs' => []])
{{-- Mobile-first read-only portal for Shareholders and Customers --}}
<!doctype html>
<html lang="en-IN">
<head>
    <x-head-common :charts="true" />
    <title>{{ $title }} · Vector7</title>
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-ground">
<div class="mx-auto min-h-screen max-w-xl pb-24">
    {{ $slot }}
</div>
<nav class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-white pb-[env(safe-area-inset-bottom)]" aria-label="Primary">
    <div class="mx-auto flex h-[72px] max-w-xl px-2">
        @foreach ($tabs as [$label, $icon, $url, $active])
            <a href="{{ $url }}" class="tab-link {{ $active ? 'active' : '' }}"><x-icon :name="$icon" class="h-[22px] w-[22px]" />{{ $label }}</a>
        @endforeach
        <form method="POST" action="{{ route('logout') }}" class="flex flex-1">@csrf
            <button class="tab-link"><x-icon name="logout" class="h-[22px] w-[22px]" />Sign out</button>
        </form>
    </div>
</nav>
</body>
</html>
