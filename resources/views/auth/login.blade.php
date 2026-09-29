<x-layouts.auth title="Sign in">
    <h1 class="text-[26px] font-extrabold">Welcome back</h1>
    <p class="mt-1 text-ink-muted">Sign in to your Vector7 workspace.</p>
    <form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-4">
        @csrf
        <x-field name="email" type="email" label="Email" autocomplete="username" required autofocus />
        <x-field name="password" type="password" label="Password" autocomplete="current-password" required />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="check"> Keep me signed in on this device</label>
        <button type="submit" class="btn-primary h-[52px] text-[15px]">Sign in</button>
    </form>
    <div class="mt-5 flex justify-between text-sm">
        <a href="{{ route('password.request') }}">Forgot password?</a>
        <a href="{{ route('register') }}">Create a workspace</a>
    </div>
</x-layouts.auth>
