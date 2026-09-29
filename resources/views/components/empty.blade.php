@props(['title', 'icon' => 'doc'])
<div class="flex flex-col items-center gap-2 px-4 py-10 text-center">
    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cream-100 text-navy"><x-icon :name="$icon" /></span>
    <p class="font-semibold">{{ $title }}</p>
    <div class="max-w-sm text-sm text-ink-muted">{{ $slot }}</div>
</div>
