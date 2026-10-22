<x-layouts.auth title="Preview sign-in">
    <span class="badge-amber">Test preview</span>
    <h1 class="mt-2 text-2xl font-extrabold">vector7 is in testing</h1>
    <p class="mt-1 text-sm text-muted">Sign in with the preview email and password to continue.</p>
    <form method="POST" action="{{ route('preview.login.post') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="email" type="email" label="Email" required autocomplete="username" autofocus />
        <x-password :policy="false" />
        <button class="btn-primary w-full btn-pill py-3">Continue</button>
    </form>
</x-layouts.auth>
