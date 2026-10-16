<x-layouts.workspace title="Plans">
    <x-page-header title="Subscription plans" subtitle="Design Starter, Growth and Enterprise on storage, projects, plots, users and active listings. Limits are enforced everywhere with an “Upgrade or top up” message.">
        @can('plans.create')<a href="{{ route('app.plans.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> New plan</a>@endcan
    </x-page-header>
    @include('app.plans.tabs')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($plans as $p)
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
                    @foreach (\App\Services\PlanLimiter::PLAN_ITEMS as $k)
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
                <p class="mt-3 text-xs text-muted">Modules: {{ collect($p->modules ?? [])->map(fn ($m) => config('permissions.plan_modules.'.$m))->implode(', ') }}</p>
                <p class="mt-1 text-xs text-muted">{{ $counts[$p->id] ?? 0 }} tenant(s) on this plan</p>
                <div class="mt-4 flex gap-2">
                    @can('plans.update')<a href="{{ route('app.plans.edit', $p) }}" class="btn-light btn-sm">Edit</a>@endcan
                    @can('plans.delete')<x-confirm :action="route('app.plans.destroy', $p)" method="DELETE" message="Delete plan {{ $p->name }}?" class="btn-ghost btn-sm text-red-700">Delete</x-confirm>@endcan
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.workspace>
