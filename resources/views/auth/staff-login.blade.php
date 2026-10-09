<x-layouts.auth title="Workspace sign in">
    <h1 class="text-2xl font-extrabold">Sign in to your workspace</h1>
    <p class="mt-1 text-sm text-muted">For promoters, their teams and vector7 staff.</p>
    <form method="POST" action="{{ route('staff.login.post') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="email" type="email" label="Email" required autocomplete="username" autofocus />
        <x-password :policy="false" />
        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="Keep me signed in" />
            <a href="{{ route('password.request') }}" class="text-sm font-semibold">Forgot password?</a>
        </div>
        <button class="btn-primary w-full btn-pill py-3">Sign in</button>
    </form>
    <x-slot:below>New promoter? <a href="{{ route('signup') }}" class="font-semibold">Create a workspace</a> · Buying a plot? <a href="{{ route('login') }}" class="font-semibold">Customer sign in</a></x-slot:below>
</x-layouts.auth>
