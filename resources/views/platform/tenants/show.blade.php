<x-layouts.platform :title="$tenant->name">
    <x-page-header :title="$tenant->name" :subtitle="$tenant->email.' · '.$tenant->phone.' · joined '.$tenant->created_at->format('j M Y')" :back="route('platform.tenants.index')">
        <x-slot:actions><x-status :status="$tenant->status" /></x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Plan" :value="$tenant->plan?->name ?? '—'" :note="ucfirst($tenant->subscription?->cycle ?? '')" />
        <x-kpi label="Users" :value="$users->count().' / '.($tenant->plan?->max_users ?? '—')" />
        <x-kpi label="Layout projects" :value="$usage['layouts'].' / '.($tenant->plan?->max_layouts ?? '—')" />
        <x-kpi label="Plots" :value="number_format($usage['plots']).' / '.number_format($tenant->plan?->max_plots ?? 0)" />
    </section>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="card-pad flex flex-col gap-4">
            <h2 class="section-title">Actions</h2>
            <form method="POST" action="{{ route('platform.tenants.plan', $tenant) }}" class="flex items-end gap-2">@csrf
                <x-select name="plan_id" label="Change plan" :options="$plans->pluck('name', 'id')" :value="$tenant->plan_id" class="flex-1" />
                <button class="btn-ghost">Apply</button>
            </form>
            <form method="POST" action="{{ route('platform.tenants.trial', $tenant) }}" class="flex items-end gap-2">@csrf
                <x-field name="days" type="number" label="Extend trial (days)" value="7" class="flex-1" />
                <button class="btn-ghost">Extend</button>
            </form>
            <form method="POST" action="{{ route('platform.tenants.status', $tenant) }}" class="flex flex-col gap-2" data-confirm="Change this tenant's status?">@csrf
                <input type="hidden" name="status" value="{{ $tenant->status->value === 'suspended' ? 'active' : 'suspended' }}">
                <x-field name="reason" label="Reason" required />
                <button class="{{ $tenant->status->value === 'suspended' ? 'btn-primary' : 'btn-danger' }}">{{ $tenant->status->value === 'suspended' ? 'Reactivate' : 'Suspend (read-only)' }}</button>
            </form>
            <form method="POST" action="{{ route('platform.tenants.impersonate', $tenant) }}" class="flex flex-col gap-2 rounded-xl border border-red-200 bg-red-50 p-3" data-confirm="Sign in as this tenant's Admin for 30 minutes? This is logged.">@csrf
                <p class="text-sm font-semibold text-red-800">Impersonate Admin</p>
                <x-field name="reason" label="Support reason (min 10 characters)" required />
                <button class="btn-danger btn-sm">Start 30-minute session</button>
            </form>
        </section>
        <section class="card-pad lg:col-span-2">
            <h2 class="section-title mb-2">Users</h2>
            @foreach ($users as $u)
                <div class="flex items-center gap-3 border-t border-line-soft py-2.5 text-sm first:border-t-0"><span class="flex-1"><span class="block font-semibold">{{ $u->name }}</span><span class="text-xs text-ink-muted">{{ $u->email }} · last login {{ $u->last_login_at?->diffForHumans() ?? 'never' }}</span></span><span class="badge-os">{{ $u->role->label() }}</span></div>
            @endforeach
            <h2 class="section-title mb-2 mt-5">Invoices</h2>
            @forelse ($tenant->invoices as $inv)
                <div class="flex justify-between border-t border-line-soft py-2 text-sm first:border-t-0"><span>{{ $inv->invoice_no }} · {{ $inv->created_at->format('j M Y') }}</span><span class="num">@inr($inv->total, true) · {{ ucfirst($inv->status) }}</span></div>
            @empty
                <p class="text-sm text-ink-muted">No invoices.</p>
            @endforelse
            <h2 class="section-title mb-2 mt-5">Recent audit trail</h2>
            @foreach ($audit as $a)
                <p class="border-t border-line-soft py-1.5 text-xs first:border-t-0"><span class="font-semibold">{{ $a->action }}</span> {{ $a->auditable_type }} #{{ $a->auditable_id }} · {{ $a->created_at->format('j M H:i') }}</p>
            @endforeach
        </section>
    </div>
</x-layouts.platform>
