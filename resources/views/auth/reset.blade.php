<x-layouts.auth title="Choose a new password">
    <h1 class="text-2xl font-extrabold">Choose a new password</h1>
    <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" type="email" label="Email" required :value="$email" autocomplete="username" />
        <x-password label="New password" autocomplete="new-password" />
        <x-password name="password_confirmation" label="Confirm new password" :policy="false" autocomplete="new-password" />
        <button class="btn-primary w-full btn-pill py-3">Save password</button>
    </form>
</x-layouts.auth>
