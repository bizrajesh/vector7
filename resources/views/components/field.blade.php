@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'id' => null])
@php($id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name))
@php($key = str_replace(['[', ']'], ['.', ''], $name))
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }} @if ($required)<span class="text-red-700" aria-hidden="true">*</span>@endif</label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($key, $value) }}"
        @if ($required) required aria-required="true" @endif
        @error($key) aria-invalid="true" aria-describedby="{{ $id }}_err" @enderror
        {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($key) ? ' input-error' : '')]) }}>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($key)<p class="error" id="{{ $id }}_err">{{ $message }}</p>@enderror
</div>
