@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div class="flex min-w-0 items-center gap-2">
        @if ($back)
            <a href="{{ $back }}" class="-ml-2 flex h-11 w-11 items-center justify-center rounded-xl text-ink hover:bg-white" aria-label="Back"><x-icon name="back" /></a>
        @endif
        <div class="min-w-0">
            <h1 class="truncate text-[22px] font-bold sm:text-[26px]">{{ $title }}</h1>
            @if ($subtitle)<p class="text-[13.5px] text-ink-muted">{{ $subtitle }}</p>@endif
        </div>
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
