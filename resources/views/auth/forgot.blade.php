<x-layouts.auth title="Forgot password">
    <h1 class="text-2xl font-extrabold">Forgot your password?</h1>
    <p class="mt-1 text-sm text-muted">Enter your email and we'll send a reset link. The link is valid for 60 minutes and works once.</p>
    <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
        @csrf
        <x-field name="email" type="email" label="Email" required autocomplete="email" autofocus />
        <button class="btn-primary w-full btn-pill py-3">Send reset link</button>
    </form>
    <x-slot:below><a href="{{ $back }}" class="font-semibold">Back to sign in</a></x-slot:below>
</x-layouts.auth>
