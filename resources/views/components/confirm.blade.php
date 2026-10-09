{{-- A form button that asks for confirmation (handled by app.js, no window.confirm). --}}
@props(['action', 'method' => 'POST', 'message' => 'Are you sure?', 'class' => 'btn-light btn-sm', 'password' => false])
<form method="POST" action="{{ $action }}" class="inline" data-confirm="{{ $message }}" @if ($password) data-confirm-password @endif>
    @csrf
    @if (strtoupper($method) !== 'POST') @method($method) @endif
    @if ($password)<input type="hidden" name="current_password" value="">@endif
    <button type="submit" class="{{ $class }}">{{ $slot }}</button>
</form>
