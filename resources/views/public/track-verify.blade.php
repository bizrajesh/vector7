<x-layouts.auth title="Enter your code">
    <h1 class="anim-up text-[26px] font-extrabold">Enter your code</h1>
    <p class="anim-up mt-1 text-ink-2" style="--d: 80ms">If <strong>{{ $email }}</strong> has a booking, a code is on its way. It expires in 10 minutes.</p>
    <form method="POST" action="{{ route('track.verify.submit') }}" class="anim-up mt-6 flex flex-col gap-4" style="--d: 160ms">
        @csrf
        <x-field name="code" label="6-digit code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" class="[&_input]:text-center [&_input]:text-2xl [&_input]:tracking-[0.5em]" />
        <button type="submit" class="btn-primary h-[52px] text-base">View my booking</button>
    </form>
    <a href="{{ route('track') }}" class="mt-4 text-center text-sm">Use a different email</a>
</x-layouts.auth>
