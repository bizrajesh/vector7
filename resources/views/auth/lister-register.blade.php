<x-layouts.auth :title="__('List your property')">
    <span class="badge-teal">{{ __('Property lister') }}</span>
    <h1 class="mt-2 text-2xl font-extrabold">{{ __('List your property on vector7') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('Owners and agents: post flats, houses, villas, plots, farm land and shops, get enquiries and call-back requests, and track every listing.') }}</p>
    @if ($plans->isNotEmpty())
        <ul class="mt-4 space-y-1 rounded-xl bg-page p-3 text-sm">
            <li class="font-semibold">{{ __(':n active listings free, always', ['n' => \App\Services\PlanLimiter::freeListings()]) }}</li>
            @foreach ($plans as $p)
                <li class="flex justify-between gap-2"><span>{{ __($p->name) }} · {{ __(':n listings', ['n' => $p->limit('listings') < 0 ? __('Unlimited') : $p->limit('listings')]) }}</span><span class="font-semibold">@inr($p->price)<span class="text-xs text-muted">/{{ __('month') }}</span></span></li>
            @endforeach
            <li class="text-xs text-muted">{{ __('Start with a :days-day free trial. No card needed.', ['days' => $plans->first()->trial_days ?: 14]) }}</li>
        </ul>
    @endif
    <form method="POST" action="{{ route('lister.register.store') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="admin_name" :label="__('Your name')" required autocomplete="name" />
        <x-field name="name" :label="__('Agency or business name (optional)')" autocomplete="organization" />
        <x-field name="email" type="email" :label="__('Email')" required autocomplete="email" />
        <x-field name="mobile" type="tel" :label="__('Mobile')" required inputmode="numeric" maxlength="10" :hint="__('10-digit mobile number')" autocomplete="tel-national" />
        <x-field name="city" :label="__('City')" autocomplete="address-level2" />
        <x-password :label="__('Password')" autocomplete="new-password" />
        <x-password name="password_confirmation" :label="__('Confirm password')" :policy="false" autocomplete="new-password" />
        <x-checkbox name="terms" :label="__('I agree to the Terms and the Privacy Policy')" required />
        <button class="btn-primary w-full btn-pill py-3">{{ __('Create lister account') }}</button>
    </form>
    <x-slot:below>{{ __('Already listing with us?') }} <a href="{{ route('lister.login') }}" class="font-semibold">{{ __('Sign in') }}</a> · {{ __('Layout promoter?') }} <a href="{{ route('signup') }}" class="font-semibold">{{ __('Create a promoter workspace') }}</a></x-slot:below>
</x-layouts.auth>
