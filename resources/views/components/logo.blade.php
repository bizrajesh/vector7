@props(['variant' => 'light', 'class' => 'h-10 w-auto'])
@php($file = ['light' => 'logo-light', 'dark' => 'logo-dark', 'compact' => 'logo-compact'][$variant])
<picture>
    <source srcset="{{ asset('images/brand/'.$file.'.webp') }}" type="image/webp">
    <img src="{{ asset('images/brand/'.$file.'.png') }}" alt="vector7 — Realty Manage Portal" class="{{ $class }}" width="{{ $variant === 'dark' ? 1354 : 1274 }}" height="{{ $variant === 'compact' ? 374 : ($variant === 'dark' ? 609 : 529) }}">
</picture>
