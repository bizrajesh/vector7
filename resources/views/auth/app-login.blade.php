<x-layouts.auth title="vector7 App admin">
    <p class="inline-flex items-center gap-1.5 rounded-full bg-navy px-3 py-1 text-xs font-bold uppercase tracking-wide text-white"><x-icon name="shield" class="h-3.5 w-3.5" /> App admin</p>
    <h1 class="mt-3 text-2xl font-extrabold">vector7 App sign in</h1>
    <p class="mt-1 text-sm text-muted">For the vector7 App team only. Promoters sign in to their workspace from the marketplace.</p>
    <form method="POST" action="{{ route('app.login.post') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="email" type="email" label="Email" required autocomplete="username" autofocus />
        <x-password :policy="false" />
        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="Keep me signed in" />
            <a href="{{ route('password.request') }}" class="text-sm font-semibold">Forgot password?</a>
        </div>
        <button class="btn-primary w-full btn-pill py-3">Sign in</button>
    </form>
    <x-slot:below>All sign-ins are logged.</x-slot:below>
</x-layouts.auth>
