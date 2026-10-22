<x-layouts.auth :title="__('Sign-ups are closed')">
    <h1 class="text-2xl font-extrabold">{{ __('New accounts are not open yet') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('vector7 is being tested. Creating buyer, property lister and promoter accounts will open soon. If you already have an account, sign in.') }}</p>
    <div class="mt-6 grid gap-2">
        <a href="{{ route('login') }}" class="btn-primary btn-pill">{{ __('Buyer sign in') }}</a>
        <a href="{{ route('lister.login') }}" class="btn-light btn-pill">{{ __('Property lister sign in') }}</a>
        <a href="{{ route('staff.login') }}" class="btn-light btn-pill">{{ __('Promoter workspace') }}</a>
    </div>
</x-layouts.auth>
