@props(['label', 'value', 'icon' => null, 'sub' => null, 'tone' => 'teal'])
<div class="card card-pad flex items-start gap-4">
    @if ($icon)<div class="rounded-xl p-2.5 {{ $tone === 'gold' ? 'bg-amber-100 text-amber-800' : ($tone === 'red' ? 'bg-red-100 text-red-700' : ($tone === 'navy' ? 'bg-navy-50 text-navy' : 'bg-teal-50 text-teal-700')) }}">{!! \App\Support\Icons::svg($icon, 'h-6 w-6') !!}</div>@endif
    <div class="min-w-0">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</p>
        <p class="mt-1 text-2xl font-extrabold tabular-nums text-navy">{{ $value }}</p>
        @if ($sub)<p class="mt-0.5 text-xs text-muted">{{ $sub }}</p>@endif
    </div>
</div>
