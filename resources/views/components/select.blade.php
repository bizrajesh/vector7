@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false])
@php
    $id = 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $dot = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($dot, $value);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}@if ($required) <span class="text-red-700" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif {{ $attributes->except('class')->merge(['class' => 'input']) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected($current === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($dot)<p class="error">{{ $message }}</p>@enderror
</div>
