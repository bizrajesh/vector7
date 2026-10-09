@props(['title' => 'Nothing here yet', 'icon' => 'folder'])
<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="rounded-2xl bg-teal-50 p-4 text-teal-700">{!! \App\Support\Icons::svg($icon, 'h-8 w-8') !!}</div>
    <p class="mt-4 font-bold text-navy">{{ $title }}</p>
    @if (trim($slot))<div class="mt-1 max-w-md text-sm text-muted">{{ $slot }}</div>@endif
</div>
