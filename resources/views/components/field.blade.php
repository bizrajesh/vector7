@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
@php
    $id = 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}@if ($required) <span class="text-red-700" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($dot, $value) }}"
        @if ($required) required @endif
        {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($dot) ? ' input-error' : '')]) }}
        @if ($errors->has($dot)) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif>
    @if ($help)<p class="help">{{ $help }}</p>@endif
    @error($dot)<p id="{{ $id }}-err" class="error">{{ $message }}</p>@enderror
</div>
