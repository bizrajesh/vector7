<x-layouts.auth title="Hold plot {{ $plot->plot_no }}">
    <a href="{{ route('projects.show', [$tenantModel->slug, \Illuminate\Support\Str::lower($layout->code)]) }}" class="mb-3 inline-flex items-center gap-1 text-sm font-semibold"><x-icon name="back" class="h-4 w-4" />{{ $layout->name }}</a>
    <p class="text-[12.5px] font-semibold text-ink-muted">Step 1 of 2</p>
    <h1 class="anim-up mt-1 text-[26px] font-extrabold leading-tight">Hold plot {{ $plot->plot_no }}</h1>
    <div class="anim-up card mt-4 flex items-center gap-3 px-4 py-3" style="--d: 100ms">
        <span class="tile-av h-12 w-12 shrink-0"><strong>{{ $plot->plot_no }}</strong></span>
        <span class="flex-1 text-sm"><span class="block font-bold">{{ $layout->name }}</span><span class="num text-ink-muted">{{ number_format((float) $plot->size_sqft) }} sqft · {{ $plot->facing ? ucwords(str_replace('_', ' ', $plot->facing)) : '' }} · @inr($plot->cost)</span></span>
    </div>
    <div class="anim-up mt-4 flex items-start gap-2.5 rounded-xl bg-[#FBEFD5] px-3.5 py-3 text-[13px] text-[#5E3D00]" style="--d: 150ms">
        <x-icon name="clock" class="mt-0.5 h-[18px] w-[18px] shrink-0" stroke="2" />
        <span>We hold this plot for you for <strong>{{ $hours }} hours</strong>. {{ $tenantModel->name }}'s sales team will call you to collect the booking advance and complete the purchase. No payment is taken online.</span>
    </div>
    <form method="POST" action="{{ route('booking.store', [$tenantModel->slug, \Illuminate\Support\Str::lower($layout->code), $plot->plot_no]) }}" class="anim-up mt-5 flex flex-col gap-4" style="--d: 200ms">
        @csrf
        <div class="hidden" aria-hidden="true"><label for="bk-website">Website</label><input id="bk-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <x-field name="name" label="Full name" required autocomplete="name" />
        <x-field name="phone" type="tel" label="Mobile" required inputmode="tel" autocomplete="tel" />
        <x-field name="email" type="email" label="Email" required autocomplete="email" help="We send a 6-digit code to confirm it's you. You'll use it to track your booking." />
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="check mt-0.5" required> <span>I agree to be contacted by the sales team about this plot, and to the <a href="{{ route('privacy') }}" target="_blank" rel="noopener">privacy policy</a>.</span></label>
        @error('consent')<p class="error">{{ $message }}</p>@enderror
        <button type="submit" class="btn-primary h-[52px] text-base">Send my code</button>
    </form>
</x-layouts.auth>
