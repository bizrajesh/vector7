@props(['name', 'label', 'checked' => false, 'value' => '1', 'hint' => null, 'id' => null])
@php($id = $id ?? 'cb_'.\Illuminate\Support\Str::random(8))
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="inline-flex items-start gap-2 text-sm text-navy cursor-pointer">
        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" class="checkbox mt-0.5" @checked(str_ends_with($name, '[]') ? $checked : (old() ? (bool) old(str_replace(['[', ']'], ['.', ''], $name)) : $checked)) {{ $attributes->except('class') }}>
        <span>{{ $label }} @if ($hint)<span class="block text-xs text-muted">{{ $hint }}</span>@endif</span>
    </label>
    @error(str_replace(['[', ']'], ['.', ''], $name))<p class="error">{{ $message }}</p>@enderror
</div>
