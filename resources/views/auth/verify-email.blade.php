<x-layouts.auth title="Verify your email">
    <h1 class="text-[26px] font-extrabold">Check your inbox</h1>
    <p class="mt-2 text-ink-2">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Open it to activate your workspace.</p>
    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">@csrf
        <button type="submit" class="btn-primary w-full">Resend the link</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf
        <button type="submit" class="btn-ghost w-full">Sign out</button>
    </form>
</x-layouts.auth>
