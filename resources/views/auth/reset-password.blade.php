<x-layouts.auth title="Choose a new password">
    <h1 class="text-[26px] font-extrabold">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 flex flex-col gap-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" type="email" label="Email" :value="$email" required autocomplete="username" />
        <x-field name="password" type="password" label="New password" required autocomplete="new-password" help="At least 10 characters with upper and lower case, a number and a symbol." />
        <x-field name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
        <button type="submit" class="btn-primary h-[52px]">Save password</button>
    </form>
</x-layouts.auth>
