<x-layouts.auth :title="__('Forgot password')">
    <h1 class="text-2xl font-extrabold">{{ __('Forgot your password?') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('Enter your email and we\'ll send a reset link. The link is valid for 60 minutes and works once.') }}</p>
    <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
        @csrf
        <x-field name="email" type="email" :label="__('Email')" required autocomplete="email" autofocus />
        <button class="btn-primary w-full btn-pill py-3">{{ __('Send reset link') }}</button>
    </form>
    <x-slot:below><a href="{{ $back }}" class="font-semibold">{{ __('Back to sign in') }}</a></x-slot:below>
</x-layouts.auth>
