<x-layouts.auth title="Create your account">
    <h1 class="text-2xl font-extrabold">Create your free account</h1>
    <p class="mt-1 text-sm text-muted">Book plots, track payments and keep your documents in one place.</p>
    <form method="POST" action="{{ route('customer.register') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="name" label="Full name" required autocomplete="name" />
        <x-field name="email" type="email" label="Email" required autocomplete="email" />
        <x-field name="mobile" type="tel" label="Mobile" required inputmode="numeric" maxlength="10" hint="10-digit mobile number" autocomplete="tel-national" />
        <x-password label="Password" autocomplete="new-password" />
        <x-password name="password_confirmation" label="Confirm password" :policy="false" autocomplete="new-password" />
        <x-checkbox name="terms" label="I agree to the Terms and the Privacy Policy" required />
        <button class="btn-primary w-full btn-pill py-3">Create account</button>
    </form>
    <x-slot:below>Already registered? <a href="{{ route('login') }}" class="font-semibold">Sign in</a></x-slot:below>
</x-layouts.auth>
