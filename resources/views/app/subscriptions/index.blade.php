<x-layouts.workspace title="Tenants & usage">
    <x-page-header title="Tenant subscriptions & usage" subtitle="Teal under 80% · amber 80–99% · red at 100%.">
        <x-export-buttons />
    </x-page-header>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Name, tenant ID, email" />
        <x-select name="plan" label="Plan" :options="$plans->pluck('name', 'id')" :value="request('plan')" placeholder="All plans" />
        <x-select name="status" label="Status" :options="['trial' => 'Trial', 'active' => 'Active', 'past_due' => 'Past due', 'expired' => 'Expired', 'cancelled' => 'Cancelled']" :value="request('status')" placeholder="Any" />
        <x-select name="near" label="Near limit" :options="['1' => '80% or more of any limit']" :value="request('near')" placeholder="All tenants" />
    </x-filters>
    <div class="space-y-3">
        @forelse ($rows as $r)
            @php($t = $r['tenant'])
            <a href="{{ route('app.subscriptions.show', $t) }}" class="card block p-4 text-navy no-underline transition hover:shadow-lift">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-bold">{{ $t->name }} @if ($t->status !== 'active')<span class="badge-red ml-1">Suspended</span>@endif</p>
                        <p class="text-xs text-muted">{{ $t->code }} · {{ $t->city }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="font-semibold">{{ $r['sub']?->plan->name ?? '—' }} <span class="{{ $r['sub']?->isUsable() ? 'badge-teal' : 'badge-red' }} ml-1">{{ ucfirst(str_replace('_', ' ', $r['sub']?->status ?? 'none')) }}</span></p>
                        @if ($r['sub'])<p class="text-xs text-muted">@date($r['sub']->starts_on) – @date($r['sub']->ends_on) · {{ $r['sub']->daysLeft() }} days left</p>@endif
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 md:grid-cols-4">
                    @foreach (['storage_mb', 'projects', 'ai_credits', 'users_tenant_sales'] as $k)
                        @php($u = $r['usage'][$k])
                        <div>
                            <div class="flex justify-between text-xs"><span class="text-muted">{{ $u['label'] }}</span><span class="font-semibold">{{ $u['display']['used'] }} / {{ $u['display']['limit'] }}</span></div>
                            @if ($u['unlimited'])<p class="text-xs text-muted">Unlimited</p>@else<x-progress :pct="$u['pct']" class="mt-1" :label="$u['label']" />@endif
                        </div>
                    @endforeach
                </div>
            </a>
        @empty
            <div class="card"><x-empty title="No tenants match" icon="building" /></div>
        @endforelse
    </div>
</x-layouts.workspace>
