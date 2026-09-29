<x-layouts.auth title="Track my booking">
    <h1 class="anim-up text-[26px] font-extrabold">Track my booking</h1>
    <p class="anim-up mt-1 text-ink-2" style="--d: 80ms">Enter the email you booked with. We'll send a 6-digit code — no password needed.</p>
    <form method="POST" action="{{ route('track') }}" class="anim-up mt-6 flex flex-col gap-4" style="--d: 160ms">
        @csrf
        <x-field name="email" type="email" label="Email" required autocomplete="email" />
        <button type="submit" class="btn-primary h-[52px] text-base">Send my code</button>
    </form>
    <p class="mt-6 text-center text-sm text-ink-muted">Haven't booked yet? <a href="{{ route('projects.index') }}" class="font-semibold">Browse projects</a></p>
</x-layouts.auth>
