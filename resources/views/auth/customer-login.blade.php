<x-layouts.auth :title="__('Sign in')">
    <h1 class="text-2xl font-extrabold">{{ __('Welcome back') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('Sign in to see your bookings, payments and documents.') }}</p>
    <form method="POST" action="{{ route('customer.login') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="email" type="email" :label="__('Email')" required autocomplete="username" autofocus />
        <x-password :policy="false" />
        <div class="flex items-center justify-between">
            <x-checkbox name="remember" :label="__('Keep me signed in')" />
            <a href="{{ route('customer.password.request') }}" class="text-sm font-semibold">{{ __('Forgot password?') }}</a>
        </div>
        <button class="btn-primary w-full btn-pill py-3">{{ __('Sign in') }}</button>
    </form>
    <x-slot:below>@if (\App\Support\Signups::open('buyer')){{ __('New here?') }} <a href="{{ route('register') }}" class="font-semibold">{{ __('Create a free account') }}</a> @endif<br>{{ __('Listing a property?') }} <a href="{{ route('lister.login') }}" class="font-semibold">{{ __('Property lister sign in') }}</a> {{ __('· Promoter?') }} <a href="{{ route('staff.login') }}" class="font-semibold">{{ __('Workspace sign in') }}</a></x-slot:below>
</x-layouts.auth>
