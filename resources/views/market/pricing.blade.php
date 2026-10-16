<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    @php
        $isListings = $for === 'listings';
        $points = $isListings
            ? [__('Post plots, flats, villas, houses, farm land and shops with 3–20 photos, video and a map pin'), __('Buyers call, WhatsApp or email you — every contact lands in Leads'), __('Follow-up reminders, lead stages and reports for your sales team'), __('Masked numbers and consent stop spam calls')]
            : [__('Estimate the layout and track every approval stage with its documents'), __('Quick launch an already-approved layout with photos, plots and offers'), __('Online booking, instalments, receipts and registration packs'), __('Leads, follow-ups and reports for your sales team')];
    @endphp
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
            <nav class="mb-6 inline-flex rounded-full bg-page p-1 text-sm" aria-label="{{ __('I want to') }}">
                <a href="{{ route('market.pricing') }}" class="rounded-full px-4 py-2 font-semibold no-underline {{ ! $isListings ? 'bg-navy text-white' : 'text-navy' }}" @if (! $isListings) aria-current="page" @endif>{{ __('Sell plots in layouts') }}</a>
                <a href="{{ route('market.pricing', ['for' => 'listings']) }}" class="rounded-full px-4 py-2 font-semibold no-underline {{ $isListings ? 'bg-navy text-white' : 'text-navy' }}" @if ($isListings) aria-current="page" @endif>{{ __('Post individual properties') }}</a>
            </nav>
            <h1 class="max-w-3xl text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $isListings ? __('Reach serious buyers and tenants in Tamil Nadu') : __('One workspace from farmland to sale deed') }}</h1>
            <ul class="mt-6 grid max-w-3xl gap-2 text-lg text-muted sm:grid-cols-2">
                @foreach ($points as $pt)<li class="flex gap-2"><span class="mt-1 text-teal-700">{!! \App\Support\Icons::svg('check', 'h-5 w-5') !!}</span>{{ $pt }}</li>@endforeach
            </ul>
            <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('signup') }}" class="btn-primary btn-pill px-8 py-3">{{ __('Start free trial') }}</a><a href="{{ route('staff.login') }}" class="btn-light btn-pill px-8 py-3">{{ __('Promoter sign in') }}</a></div>
        </div>
    </section>
    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ($plans as $p)
                <div class="card card-pad flex flex-col {{ $loop->index === 1 ? 'ring-2 ring-teal' : '' }}">
                    <h2 class="text-2xl font-extrabold">{{ $p->name }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ __($p->description) }}</p>
                    <p class="mt-5 text-4xl font-extrabold">@inr($p->price)<span class="text-base font-semibold text-muted"> / {{ $p->billing_cycle === 'yearly' ? __('year') : __('month') }}</span></p>
                    <p class="text-xs text-muted">{{ __('+ GST · :days-day free trial', ['days' => $p->trial_days]) }}</p>
                    <ul class="mt-5 flex-1 space-y-2 text-sm">
                        @php($lim = fn ($k) => ($v = $p->limit($k)) < 0 ? __('Unlimited') : ($k === 'listings' ? max($v, \App\Services\PlanLimiter::freeListings()) : $v))
                        @php($tick = \App\Support\Icons::svg('check', 'h-4 w-4 mt-0.5'))
                        @if ($isListings)
                            <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __(':n live property listings', ['n' => $lim('listings')]) }}</li>
                            <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __('Up to :n photos per listing', ['n' => $lim('images_per_listing')]) }}</li>
                        @else
                            <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __(':n active projects', ['n' => $lim('projects')]) }}</li>
                            <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __(':n plots', ['n' => $lim('plots')]) }}</li>
                            <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __(':n live property listings', ['n' => $lim('listings')]) }}</li>
                        @endif
                        <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __(':size document storage', ['size' => $p->limit('storage_mb') < 0 ? __('Unlimited') : \App\Support\Format::bytes($p->limit('storage_mb') * 1048576)]) }}</li>
                        <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __(':n users (any role)', ['n' => $lim('users')]) }}</li>
                        <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __(':n AI credits a month', ['n' => $lim('ai_credits')]) }}</li>
                        <li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __('Top up users, projects, plots, storage or listings any time') }}</li>
                        @foreach (config('permissions.plan_modules') as $m => $label)
                            @if ($p->hasModule($m))<li class="flex gap-2"><span class="text-teal-700">{!! $tick !!}</span>{{ __($label) }}</li>@endif
                        @endforeach
                    </ul>
                    <a href="{{ route('signup') }}" class="{{ $loop->index === 1 ? 'btn-primary' : 'btn-light' }} btn-pill mt-6">{{ __('Start with :plan', ['plan' => $p->name]) }}</a>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.public>
