<x-layouts.auth title="Choose your plan">
    <p class="text-[12.5px] font-semibold text-ink-muted">Step 1 of 2</p>
    <h1 class="mt-1 text-[26px] font-extrabold leading-tight">Run your layouts, from land to registration</h1>
    <p class="mt-1.5 text-sm text-ink-muted">Pick a plan for your business. You become the Admin and invite your team.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-5 flex flex-col gap-4">
        @csrf
        <fieldset>
            <legend class="sr-only">Billing cycle</legend>
            <div class="flex rounded-xl bg-[#E9E6D6] p-1">
                @foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly · save 2 months'] as $value => $label)
                    <label class="flex-1">
                        <input type="radio" name="cycle" value="{{ $value }}" class="peer sr-only" @checked(old('cycle', $cycle) === $value)>
                        <span class="flex h-10 cursor-pointer items-center justify-center rounded-[9px] text-sm font-semibold text-ink-2 peer-checked:bg-white peer-checked:text-ink peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-sage">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="flex flex-col gap-3">
            <legend class="sr-only">Plan</legend>
            @foreach ($plans as $plan)
                <label class="cursor-pointer">
                    <input type="radio" name="plan" value="{{ $plan->code }}" class="peer sr-only" @checked(old('plan', $selectedPlan?->code) === $plan->code)>
                    <span class="relative flex flex-col gap-1.5 rounded-2xl border-[1.5px] border-line bg-white px-4 py-3.5 peer-checked:border-2 peer-checked:border-teal peer-checked:shadow-card peer-focus-visible:ring-2 peer-focus-visible:ring-sage">
                        @if ($plan->badge)<span class="absolute -top-2.5 right-4 rounded-full bg-sage px-2.5 py-0.5 text-[11px] font-bold uppercase text-navy">{{ $plan->badge }}</span>@endif
                        <span class="flex items-center justify-between">
                            <span class="text-base font-bold">{{ $plan->name }}</span>
                            <span class="num text-[15px] font-bold">@inr($plan->price_monthly)<span class="text-xs font-medium text-ink-muted">/mo</span></span>
                        </span>
                        <span class="text-[12.5px] text-ink-2">{{ $plan->max_users }} users · {{ $plan->max_layouts }} layout {{ \Illuminate\Support\Str::plural('project', $plan->max_layouts) }} · {{ number_format($plan->max_plots) }} plots</span>
                    </span>
                </label>
            @endforeach
            @error('plan')<p class="error">{{ $message }}</p>@enderror
        </fieldset>

        <p class="pt-2 text-[12.5px] font-semibold text-ink-muted">Step 2 of 2 · Your business</p>
        <x-field name="business_name" label="Business name" required autocomplete="organization" />
        <x-field name="name" label="Your name" required autocomplete="name" />
        <x-field name="email" type="email" label="Work email" required autocomplete="email" />
        <x-field name="phone" type="tel" label="Mobile" required inputmode="tel" autocomplete="tel" />
        <x-field name="gstin" label="GSTIN (optional)" autocapitalize="characters" />
        <x-field name="password" type="password" label="Password" required autocomplete="new-password" help="At least 10 characters with upper and lower case, a number and a symbol." />
        <x-field name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="terms" value="1" class="check mt-0.5" required> <span>I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener">terms</a> and <a href="{{ route('privacy') }}" target="_blank" rel="noopener">privacy policy</a>.</span></label>

        <button type="submit" class="btn-primary h-[54px] text-base">Create my workspace</button>
        <p class="text-center text-[12.5px] text-ink-muted">Includes a free trial · GST extra · <a href="{{ route('login') }}" class="font-semibold">Sign in</a></p>
    </form>
</x-layouts.auth>
