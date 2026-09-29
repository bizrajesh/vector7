@props(['label', 'value', 'note' => null, 'tone' => 'muted'])
@php
    $toneClass = ['good' => 'text-[#1F6B45]', 'bad' => 'text-[#A12622]', 'warn' => 'text-[#7A4F00]', 'muted' => 'text-ink-muted'][$tone] ?? 'text-ink-muted';
@endphp
<div {{ $attributes->merge(['class' => 'card-pad flex flex-col gap-1.5']) }}>
    <span class="kpi-label">{{ $label }}</span>
    <span class="kpi-value">{{ $value }}</span>
    @if ($note)<span class="text-[12.5px] font-semibold {{ $toneClass }}">{{ $note }}</span>@endif
    {{ $slot }}
</div>
