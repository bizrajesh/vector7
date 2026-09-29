@props(['title'])
<!doctype html>
<html lang="en-IN">
<head>
    <x-head-common />
    <title>{{ $title }} · Vector7</title>
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-ground">
<main class="mx-auto flex min-h-screen max-w-md flex-col px-5 py-8">
    <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2.5 text-ink no-underline hover:text-ink">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-navy text-[12px] font-extrabold tracking-tight text-sage">V7</span>
        <span class="text-lg font-bold">Vector7</span>
    </a>
    <x-flash />
    {{ $slot }}
</main>
</body>
</html>
