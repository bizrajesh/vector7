@props(['name' => 'password', 'label' => 'Password', 'hint' => \App\Support\Passwords::HINT, 'autocomplete' => 'current-password', 'policy' => true, 'id' => null])
@php($id = $id ?? 'f_'.$name)
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }} <span class="text-red-700" aria-hidden="true">*</span></label>
    <div class="relative">
        <input id="{{ $id }}" name="{{ $name }}" type="password" required autocomplete="{{ $autocomplete }}"
            @if ($policy) minlength="8" maxlength="32" data-password-policy @endif
            class="input pr-11 @error($name) input-error @enderror" @error($name) aria-invalid="true" @enderror>
        <button type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-muted hover:text-navy" data-toggle-password="{{ $id }}" aria-label="Show password" aria-pressed="false">
            <span data-eye>{!! \App\Support\Icons::svg('eye') !!}</span><span data-eye-off class="hidden">{!! \App\Support\Icons::svg('eye-off') !!}</span>
        </button>
    </div>
    @if ($policy && $hint)<p class="hint">{{ $hint }}</p>@endif
    <p class="error hidden" data-password-error="{{ $id }}" role="alert"></p>
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
