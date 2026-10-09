@props(['name', 'label' => null, 'value' => null, 'rows' => 4, 'hint' => null, 'required' => false, 'id' => null])
@php($id = $id ?? 'f_'.str_replace(['[', ']', '.'], '_', $name))
@php($key = str_replace(['[', ']'], ['.', ''], $name))
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="label">{{ $label }} @if ($required)<span class="text-red-700" aria-hidden="true">*</span>@endif</label>@endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($key) ? ' input-error' : '')]) }}>{{ old($key, $value) }}</textarea>
    @if ($hint)<p class="hint">{{ $hint }}</p>@endif
    @error($key)<p class="error">{{ $message }}</p>@enderror
</div>
