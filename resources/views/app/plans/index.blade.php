<x-layouts.workspace title="Plans">
    <x-page-header title="Subscription plans" subtitle="Promoter plans (Starter, Growth, Enterprise) and Property lister plans. Limits are enforced everywhere with an “Upgrade or top up” message.">
    </x-page-header>
    @include('app.plans.tabs')
    @foreach (\App\Models\Plan::AUDIENCES as $aud => $audLabel)
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold">{{ $aud === 'lister' ? 'Property lister plans' : 'Promoter plans' }}</h2>
                <p class="text-sm text-muted">{{ $aud === 'lister' ? 'For independent owners and agents who only post and track listings. They sign up from the marketplace.' : 'For layout promoters and their teams: projects, plots, bookings, sales and listings.' }}</p>
            </div>
            @can('plans.create')<a href="{{ route('app.plans.create', ['audience' => $aud]) }}" class="btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> New {{ $aud === 'lister' ? 'lister' : 'promoter' }} plan</a>@endcan
        </div>
        <div class="mb-10 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($plans[$aud] ?? [] as $p)
            <div class="card card-pad flex flex-col {{ $p->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-xl font-extrabold">{{ $p->name }}</h2>
                    <div class="flex flex-wrap gap-1">
                        @if ($p->is_trial_default)<span class="badge-amber">Trial default</span>@endif
                        <span class="{{ $p->is_active ? 'badge-teal' : 'badge-gray' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
                <p class="mt-1 text-sm text-muted">{{ $p->description }}</p>
                <p class="mt-3 text-2xl font-extrabold">@inr($p->price) <span class="text-sm text-muted">/ {{ $p->billing_cycle }} · {{ $p->trial_days }}-day trial</span></p>
                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                    @foreach ($p->audience === 'lister' ? ['listings', 'storage_mb'] : \App\Services\PlanLimiter::PLAN_ITEMS as $k)
                        <dt class="font-semibold">{{ \App\Services\PlanLimiter::LIMIT_LABELS[$k] }}</dt><dd class="text-right font-extrabold">@include('app.plans.limit-value', ['k' => $k, 'v' => $p->limit($k)])</dd>
                    @endforeach
                </dl>
                <dl class="mt-2 grid flex-1 grid-cols-2 gap-x-3 gap-y-1 border-t border-navy-50 pt-2 text-xs">
                    @foreach (['images_per_listing', 'ai_credits'] as $k)
                        <dt class="text-muted">{{ \App\Services\PlanLimiter::LIMIT_LABELS[$k] }}</dt><dd class="text-right font-semibold">@include('app.plans.limit-value', ['k' => $k, 'v' => $p->limit($k)])</dd>
                    @endforeach
                    @foreach (\App\Services\PlanLimiter::ROLE_CAP_KEYS as $k)
                        @if ($p->limit($k) >= 0)<dt class="text-muted">{{ \App\Services\PlanLimiter::LIMIT_LABELS[$k] }} (cap)</dt><dd class="text-right font-semibold">{{ $p->limit($k) }}</dd>@endif
                    @endforeach
                </dl>
                @if ($p->audience !== 'lister')<p class="mt-3 text-xs text-muted">Modules: {{ collect($p->modules ?? [])->map(fn ($m) => config('permissions.plan_modules.'.$m))->implode(', ') }}</p>@endif
                <p class="mt-1 text-xs text-muted">{{ $counts[$p->id] ?? 0 }} tenant(s) on this plan</p>
                <div class="mt-4 flex gap-2">
                    @can('plans.update')<a href="{{ route('app.plans.edit', $p) }}" class="btn-light btn-sm">Edit</a>@endcan
                    @can('plans.delete')<x-confirm :action="route('app.plans.destroy', $p)" method="DELETE" message="Delete plan {{ $p->name }}?" class="btn-ghost btn-sm text-red-700">Delete</x-confirm>@endcan
                </div>
            </div>
            @empty
                <div class="card md:col-span-2 xl:col-span-3"><x-empty title="No plans yet" icon="tag" /></div>
            @endforelse
        </div>
    @endforeach
</x-layouts.workspace>
