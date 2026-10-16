<x-layouts.auth :title="__('Choose a new password')">
    <h1 class="text-2xl font-extrabold">{{ __('Choose a new password') }}</h1>
    <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" type="email" :label="__('Email')" required :value="$email" autocomplete="username" />
        <x-password :label="__('New password')" autocomplete="new-password" />
        <x-password name="password_confirmation" :label="__('Confirm new password')" :policy="false" autocomplete="new-password" />
        <button class="btn-primary w-full btn-pill py-3">{{ __('Save password') }}</button>
    </form>
</x-layouts.auth>
