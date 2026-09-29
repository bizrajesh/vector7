@props(['title'])
<!doctype html>
<html lang="en-IN">
<head>
    <x-head-common />
    <title>{{ $title }} · Vector7</title>
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-white">
<div class="no-print sticky top-0 flex items-center justify-between border-b border-line bg-white px-4 py-3">
    <a href="{{ url()->previous() }}" class="btn-ghost btn-sm"><x-icon name="back" class="h-4 w-4" />Back</a>
    <button type="button" data-print class="btn-primary btn-sm"><x-icon name="print" class="h-4 w-4" />Print / Save as PDF</button>
</div>
<main class="mx-auto max-w-3xl px-6 py-8 text-[14px] leading-relaxed">
    {{ $slot }}
</main>
</body>
</html>
