<x-layouts.public :seo="$seo">
    <div class="mx-auto max-w-6xl px-4 py-12">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-muted"><a href="{{ route('home') }}">Home</a> / <span>Pricing</span></nav>
        <h1 class="mt-3 text-4xl font-extrabold">Plans for every layout business</h1>
        <p class="mt-3 max-w-2xl text-ink-2">All plans include the plot catalogue, bookings, instalment tracking, registration workflow and accounting. Prices exclude GST. Every plan starts with a free trial.</p>

        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @forelse ($plans as $plan)
                <article class="card-pad relative flex flex-col gap-4 {{ $plan->badge ? 'border-2 border-teal shadow-card' : '' }}">
                    @if ($plan->badge)<span class="absolute -top-3 right-5 rounded-full bg-sage px-3 py-1 text-[11px] font-bold uppercase text-navy">{{ $plan->badge }}</span>@endif
                    <div>
                        <h2 class="text-xl font-bold">{{ $plan->name }}</h2>
                        <p class="text-sm text-ink-muted">{{ $plan->description }}</p>
                    </div>
                    <p class="num text-3xl font-extrabold">@inr($plan->price_monthly)<span class="text-sm font-medium text-ink-muted">/month</span></p>
                    <p class="-mt-3 text-sm text-ink-muted">or @inr($plan->price_yearly) per year</p>
                    <ul class="flex flex-col gap-2 text-sm">
                        <li class="flex gap-2"><x-icon name="check" class="h-5 w-5 text-teal" stroke="2.4" />{{ $plan->max_users }} users</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-5 w-5 text-teal" stroke="2.4" />{{ $plan->max_layouts }} layout {{ \Illuminate\Support\Str::plural('project', $plan->max_layouts) }}</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-5 w-5 text-teal" stroke="2.4" />{{ number_format($plan->max_plots) }} plots</li>
                        @foreach (['shareholder_portal' => 'Shareholder portal & share value', 'whatsapp' => 'WhatsApp alerts', 'dss' => 'Business analytics dashboards', 'google_drive' => 'Google Drive document storage'] as $key => $label)
                            @if ($plan->hasFeature($key))<li class="flex gap-2"><x-icon name="check" class="h-5 w-5 text-teal" stroke="2.4" />{{ $label }}</li>@endif
                        @endforeach
                    </ul>
                    <a href="{{ route('register', ['plan' => $plan->code]) }}" class="{{ $plan->badge ? 'btn-primary' : 'btn-outline' }} mt-auto">Start {{ $plan->trial_days }}-day trial</a>
                </article>
            @empty
                <p class="text-ink-muted">Plans are being updated. Please contact us.</p>
            @endforelse
        </div>

        <section class="mt-14 max-w-3xl">
            <h2 class="text-2xl font-bold">Frequently asked questions</h2>
            <h3 class="mt-5 font-bold">Can I change plans later?</h3>
            <p class="mt-1 text-ink-2">Yes. Upgrades apply immediately. You can move to a smaller plan when your usage fits its limits.</p>
            <h3 class="mt-5 font-bold">What happens when the trial ends?</h3>
            <p class="mt-1 text-ink-2">You get a 7-day grace period to pay. After that the workspace becomes read-only until payment — your data is never deleted.</p>
            <h3 class="mt-5 font-bold">Who owns the data?</h3>
            <p class="mt-1 text-ink-2">You do. You can export ledgers and records at any time.</p>
        </section>
    </div>
</x-layouts.public>
