@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if ($back)<a href="{{ $back }}" class="mb-1 inline-flex items-center gap-1 text-sm font-semibold no-underline">{!! \App\Support\Icons::svg('chevron-left', 'h-4 w-4') !!} Back</a>@endif
        <h1 class="text-2xl font-extrabold sm:text-3xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>@endif
    </div>
    @if (trim($slot))<div class="flex flex-wrap gap-2">{{ $slot }}</div>@endif
</div>
