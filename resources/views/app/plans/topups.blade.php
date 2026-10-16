<x-layouts.workspace title="Top-up packs">
    <x-page-header title="Top-up packs" subtitle="Extra users, projects, plots, storage or active listings a tenant can buy on top of the plan." />
    @include('app.plans.tabs')
    @php($canEdit = auth()->user()->hasPerm('plans.update'))

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Packs on sale" :value="$packs->where('is_active', true)->count()" />
        <x-stat label="Top-ups sold this month (excl. GST)" :value="\App\Support\Format::inr($sold)" />
        <div class="card p-4 text-sm text-muted">Pack price is per month, excl. GST. A bought top-up runs until the tenant's current plan period ends, so the tenant pays for the months left in that period (rounded up).</div>
    </div>

    <div class="space-y-3">
        @foreach ($packs as $pack)
            <form method="POST" action="{{ route('app.topups.update', $pack) }}" class="card card-pad grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-7 {{ $pack->is_active ? '' : 'opacity-70' }}">
                @csrf @method('PUT')
                <x-select name="key" label="Adds to" :options="\App\Models\TopupPack::KEYS" :value="$pack->key" id="k{{ $pack->id }}" :disabled="! $canEdit" />
                <x-field name="name" label="Name shown to tenants" :value="$pack->name" required id="n{{ $pack->id }}" class="lg:col-span-2" :disabled="! $canEdit" />
                <x-field name="units" type="number" min="1" :label="$pack->key === 'storage_mb' ? 'Units (MB)' : 'Units'" :value="$pack->units" required id="u{{ $pack->id }}" :disabled="! $canEdit" />
                <x-field name="price" type="number" step="0.01" min="0" label="Price / month (₹)" :value="$pack->price" required id="p{{ $pack->id }}" :disabled="! $canEdit" />
                <x-field name="sort" type="number" min="0" label="Sort" :value="$pack->sort" id="s{{ $pack->id }}" :disabled="! $canEdit" />
                <div class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="On sale" :checked="$pack->is_active" id="a{{ $pack->id }}" />
                    @if ($canEdit)<button class="btn-primary btn-sm">Save</button>@endif
                </div>
                <p class="text-xs text-muted sm:col-span-2 lg:col-span-7">= {{ $pack->unitsLabel() }} {{ strtolower(\App\Models\TopupPack::KEYS[$pack->key] ?? '') }} for @inr($pack->price) a month + GST</p>
            </form>
            @can('plans.delete')
                <div class="-mt-2 text-right"><x-confirm :action="route('app.topups.destroy', $pack)" method="DELETE" message="Delete {{ $pack->name }}?" class="btn-ghost btn-sm text-red-700">Delete {{ $pack->name }}</x-confirm></div>
            @endcan
        @endforeach
    </div>

    @can('plans.create')
        <form method="POST" action="{{ route('app.topups.store') }}" class="card card-pad mt-6 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-6">
            @csrf
            <h2 class="section-title sm:col-span-2 lg:col-span-6">New pack</h2>
            <x-select name="key" label="Adds to" :options="\App\Models\TopupPack::KEYS" id="new_key" />
            <x-field name="name" label="Name shown to tenants" placeholder="e.g. 10 extra plots" required id="new_name" class="lg:col-span-2" />
            <x-field name="units" type="number" min="1" label="Units (storage in MB)" required id="new_units" />
            <x-field name="price" type="number" step="0.01" min="0" label="Price / month (₹)" required id="new_price" />
            <div class="flex items-center gap-3">
                <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="On sale" :checked="true" id="new_active" />
                <button class="btn-primary">Add</button>
            </div>
        </form>
    @endcan

    <div class="card mt-6">
        <h2 class="section-title p-4">Latest top-ups</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Tenant</th><th>Top-up</th><th>Source</th><th>Valid</th><th>Status</th><th>Invoice</th></tr></thead>
            <tbody>
            @forelse ($recent as $t)
                <tr>
                    <td>@if ($t->tenantRel)<a href="{{ route('app.subscriptions.show', $t->tenant_id) }}" class="font-semibold">{{ $t->tenantRel->name }}</a>@endif</td>
                    <td class="font-semibold">{{ $t->label() }}</td>
                    <td class="text-sm">{{ $t->source === 'grant' ? 'Granted' : 'Bought' }}</td>
                    <td class="text-sm">{{ $t->starts_on ? \App\Support\Format::date($t->starts_on) : '—' }} – {{ $t->ends_on ? \App\Support\Format::date($t->ends_on) : 'no end' }}</td>
                    <td><span class="{{ $t->isCurrent() ? 'badge-teal' : ($t->status === 'pending' ? 'badge-amber' : 'badge-gray') }}">{{ $t->isCurrent() ? 'Active' : ($t->status === 'active' ? 'Ended' : ucfirst($t->status)) }}</span></td>
                    <td class="text-sm">@if ($t->invoice)<a href="{{ route('app.invoices.show', $t->invoice) }}" class="font-mono font-semibold">{{ $t->invoice->number }}</a> · @inr($t->invoice->total)@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No top-ups yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.workspace>
