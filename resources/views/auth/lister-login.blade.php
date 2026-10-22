<x-layouts.auth :title="__('Property lister sign in')">
    <span class="badge-teal">{{ __('Property lister') }}</span>
    <h1 class="mt-2 text-2xl font-extrabold">{{ __('Sign in to post and track your listings') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('For owners and agents listing flats, houses, plots, farm land and shops.') }}</p>
    <form method="POST" action="{{ route('lister.login.post') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="email" type="email" :label="__('Email')" required autocomplete="username" autofocus />
        <x-password :policy="false" />
        <div class="flex items-center justify-between">
            <x-checkbox name="remember" :label="__('Keep me signed in')" />
            <a href="{{ route('password.request') }}" class="text-sm font-semibold">{{ __('Forgot password?') }}</a>
        </div>
        <button class="btn-primary w-full btn-pill py-3">{{ __('Sign in') }}</button>
    </form>
    <x-slot:below>@if (\App\Support\Signups::open('lister')){{ __('New lister?') }} <a href="{{ route('lister.register') }}" class="font-semibold">{{ __('Create a lister account') }}</a> · @endif{{ __('Buying?') }} <a href="{{ route('login') }}" class="font-semibold">{{ __('Buyer sign in') }}</a></x-slot:below>
</x-layouts.auth>
