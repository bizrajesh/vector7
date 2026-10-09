<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
            <h1 class="max-w-3xl text-4xl font-extrabold tracking-tight sm:text-5xl">One workspace from farmland to sale deed</h1>
            <p class="mt-4 max-w-2xl text-lg text-muted">Estimate the layout, track 13 approval stages with their documents, launch plots on the marketplace, take bookings and instalments, and print registration packs.</p>
            <div class="mt-8 flex flex-wrap gap-3"><a href="{{ route('signup') }}" class="btn-primary btn-pill px-8 py-3">Start free trial</a><a href="{{ route('staff.login') }}" class="btn-light btn-pill px-8 py-3">Sign in</a></div>
        </div>
    </section>
    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ($plans as $p)
                <div class="card card-pad flex flex-col {{ $loop->index === 1 ? 'ring-2 ring-teal' : '' }}">
                    <h2 class="text-2xl font-extrabold">{{ $p->name }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ $p->description }}</p>
                    <p class="mt-5 text-4xl font-extrabold">@inr($p->price)<span class="text-base font-semibold text-muted"> / {{ $p->billing_cycle === 'yearly' ? 'year' : 'month' }}</span></p>
                    <p class="text-xs text-muted">+ GST · {{ $p->trial_days }}-day free trial</p>
                    <ul class="mt-5 flex-1 space-y-2 text-sm">
                        @php($lim = fn ($k) => ($v = $p->limit($k)) < 0 ? 'Unlimited' : $v)
                        <li class="flex gap-2"><span class="text-teal-700">{!! \App\Support\Icons::svg('check', 'h-4 w-4 mt-0.5') !!}</span>{{ $lim('projects') }} active projects</li>
                        <li class="flex gap-2"><span class="text-teal-700">{!! \App\Support\Icons::svg('check', 'h-4 w-4 mt-0.5') !!}</span>{{ $p->limit('storage_mb') < 0 ? 'Unlimited' : \App\Support\Format::bytes($p->limit('storage_mb') * 1048576) }} document storage</li>
                        <li class="flex gap-2"><span class="text-teal-700">{!! \App\Support\Icons::svg('check', 'h-4 w-4 mt-0.5') !!}</span>{{ $lim('users_tenant_sales') }} sales users, {{ $lim('users_tenant_manager') }} managers</li>
                        <li class="flex gap-2"><span class="text-teal-700">{!! \App\Support\Icons::svg('check', 'h-4 w-4 mt-0.5') !!}</span>{{ $lim('ai_credits') }} AI credits a month</li>
                        @foreach (config('permissions.plan_modules') as $m => $label)
                            @if ($p->hasModule($m))<li class="flex gap-2"><span class="text-teal-700">{!! \App\Support\Icons::svg('check', 'h-4 w-4 mt-0.5') !!}</span>{{ $label }}</li>@endif
                        @endforeach
                    </ul>
                    <a href="{{ route('signup') }}" class="{{ $loop->index === 1 ? 'btn-primary' : 'btn-light' }} btn-pill mt-6">Start with {{ $p->name }}</a>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.public>
