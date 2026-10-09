<x-layouts.workspace title="Brokers">
    <x-page-header title="Brokers" subtitle="Attach a broker to a sale to record commission." :back="route('ws.sales.index')" />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>Name</th><th>Contact</th><th class="num">Commission %</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($brokers as $b)
                <tr><td class="font-semibold">{{ $b->name }}</td><td class="text-sm">{{ $b->mobile }} {{ $b->email }}</td><td class="num">{{ $b->commission_pct ?? 'Default' }}</td>
                    <td><span class="{{ $b->is_active ? 'badge-teal' : 'badge-gray' }}">{{ $b->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-right">@can('sales.update')
                        <details class="relative" data-dropdown><summary class="btn-light btn-sm list-none cursor-pointer">Edit</summary>
                            <form method="POST" action="{{ route('ws.brokers.update', $b) }}" class="absolute right-0 z-20 mt-2 w-72 space-y-2 rounded-xl bg-white p-4 text-left shadow-lift ring-1 ring-navy-50">
                                @csrf @method('PUT')
                                <x-field name="name" label="Name" :value="$b->name" id="b{{ $b->id }}n" required />
                                <x-field name="mobile" label="Mobile" :value="$b->mobile" id="b{{ $b->id }}m" />
                                <x-field name="email" label="Email" :value="$b->email" id="b{{ $b->id }}e" />
                                <x-field name="commission_pct" type="number" step="0.01" label="Commission %" :value="$b->commission_pct" id="b{{ $b->id }}c" />
                                <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active" :checked="$b->is_active" />
                                <button class="btn-primary btn-sm">Save</button>
                            </form></details>@endcan</td></tr>
            @empty
                <tr><td colspan="5"><x-empty title="No brokers yet" icon="users" /></td></tr>
            @endforelse
            </tbody>
        </table></div></div>
        @can('sales.create')
            <form method="POST" action="{{ route('ws.brokers.store') }}" class="card card-pad space-y-3">@csrf
                <h2 class="section-title">Add broker</h2>
                <x-field name="name" label="Name" required />
                <x-field name="mobile" label="Mobile" inputmode="numeric" maxlength="10" />
                <x-field name="email" type="email" label="Email" />
                <x-field name="commission_pct" type="number" step="0.01" label="Commission %" hint="Leave blank to use the tenant default" />
                <button class="btn-primary">Add</button>
            </form>
        @endcan
    </div>
</x-layouts.workspace>
