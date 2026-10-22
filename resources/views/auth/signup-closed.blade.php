<x-layouts.auth :title="__('Sign-ups are closed')">
    <h1 class="text-2xl font-extrabold">{{ __('New accounts are not open yet') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('Creating new accounts on vector7 is not open right now. If you already have an account, sign in below.') }}</p>
    <div class="mt-6 grid gap-2">
        <a href="{{ route('login') }}" class="{{ ($kind ?? 'buyer') === 'buyer' ? 'btn-primary' : 'btn-light' }} btn-pill">{{ __('Buyer sign in') }}</a>
        <a href="{{ route('lister.login') }}" class="{{ ($kind ?? '') === 'lister' ? 'btn-primary' : 'btn-light' }} btn-pill">{{ __('Property lister sign in') }}</a>
        <a href="{{ route('staff.login') }}" class="{{ ($kind ?? '') === 'promoter' ? 'btn-primary' : 'btn-light' }} btn-pill">{{ __('Promoter workspace') }}</a>
    </div>
</x-layouts.auth>
