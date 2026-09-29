<x-layouts.auth title="Enter your code">
    <p class="text-[12.5px] font-semibold text-ink-muted">Step 2 of 2</p>
    <h1 class="anim-up mt-1 text-[26px] font-extrabold">Check your email</h1>
    <p class="anim-up mt-1 text-ink-2" style="--d: 80ms">We sent a 6-digit code to <strong>{{ $email }}</strong>. It expires in 10 minutes.</p>
    <form method="POST" action="{{ route('booking.verify.submit') }}" class="anim-up mt-6 flex flex-col gap-4" style="--d: 160ms">
        @csrf
        <x-field name="code" label="6-digit code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" class="[&_input]:text-center [&_input]:text-2xl [&_input]:tracking-[0.5em]" />
        <button type="submit" class="btn-primary h-[52px] text-base">Confirm and hold my plot</button>
    </form>
    <form method="POST" action="{{ route('booking.resend') }}" class="mt-4 text-center">@csrf
        <button class="text-sm font-semibold text-teal">Send a new code</button>
    </form>
    <a href="{{ $back }}" class="mt-4 text-center text-sm">Choose a different plot</a>
</x-layouts.auth>
