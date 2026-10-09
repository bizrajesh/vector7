@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'hint' => null, 'id' => null])
@php($id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name))
@php($key = str_replace(['[', ']'], ['.', ''], $name))
@php($current = (string) old($key, $value))
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="label">{{ $label }} @if ($required)<span class="text-red-700" aria-hidden="true">*</span>@endif</label>@endif
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($key) ? ' input-error' : '')]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $val => $text)
            <option value="{{ $val }}" @selected($current === (string) $val)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($key)<p class="error">{{ $message }}</p>@enderror
</div>
