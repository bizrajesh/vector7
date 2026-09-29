<x-layouts.auth title="Reset password">
    <h1 class="text-[26px] font-extrabold">Reset your password</h1>
    <p class="mt-1 text-ink-muted">Enter your email and we will send a reset link.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 flex flex-col gap-4">
        @csrf
        <x-field name="email" type="email" label="Email" required autocomplete="email" />
        <button type="submit" class="btn-primary h-[52px]">Send reset link</button>
    </form>
    <a href="{{ route('login') }}" class="mt-5 text-sm">Back to sign in</a>
</x-layouts.auth>
