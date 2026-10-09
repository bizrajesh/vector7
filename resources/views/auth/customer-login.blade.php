<x-layouts.auth title="Sign in">
    <h1 class="text-2xl font-extrabold">Welcome back</h1>
    <p class="mt-1 text-sm text-muted">Sign in to see your bookings, payments and documents.</p>
    <form method="POST" action="{{ route('customer.login') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="email" type="email" label="Email" required autocomplete="username" autofocus />
        <x-password :policy="false" />
        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="Keep me signed in" />
            <a href="{{ route('customer.password.request') }}" class="text-sm font-semibold">Forgot password?</a>
        </div>
        <button class="btn-primary w-full btn-pill py-3">Sign in</button>
    </form>
    <x-slot:below>New here? <a href="{{ route('register') }}" class="font-semibold">Create a free account</a> · Promoter? <a href="{{ route('staff.login') }}" class="font-semibold">Workspace sign in</a></x-slot:below>
</x-layouts.auth>
