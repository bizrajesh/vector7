<x-layouts.workspace title="Customers">
    <x-page-header title="Customers" subtitle="Marketplace customers. Enable or disable, generate a password, send a reset link or update details on request. Customers are never deleted.">
        <x-export-buttons />
    </x-page-header>
    @include('partials.generated-password')
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Name, email, mobile" />
        <x-select name="status" label="Status" :options="['active' => 'Active', 'disabled' => 'Disabled']" :value="request('status')" placeholder="All" />
    </x-filters>
    <div class="card">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-navy-50 p-4">
            <p class="text-sm text-muted">{{ number_format($customers->total()) }} customer(s)</p>
            <form method="POST" action="{{ route('app.customers.bulk') }}" id="bulk-form" class="flex items-center gap-2">@csrf
                <input type="hidden" name="must_change" value="1">
                <button class="btn-light btn-sm" data-bulk-submit="bulk-form" data-bulk-name="users"><x-icon name="download" class="h-4 w-4" /> Generate passwords (Excel)</button>
            </form>
        </div>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th class="w-8"><span class="sr-only">Select</span></th><th>Customer</th><th>City</th><th class="num">Bookings</th><th class="num">Purchases</th><th>Status</th><th>Joined</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td><input type="checkbox" class="checkbox" data-bulk="users" value="{{ $c->id }}" aria-label="Select {{ $c->name }}"></td>
                    <td><a href="{{ route('app.customers.show', $c) }}" class="font-semibold">{{ $c->name }}</a><p class="text-xs text-muted">{{ $c->email }} · {{ $c->mobile }}</p></td>
                    <td>{{ $c->city ?: '—' }}</td>
                    <td class="num">{{ $c->bookings_count }}</td>
                    <td class="num">{{ $c->sales_count }}</td>
                    <td>@if ($c->isLocked())<span class="badge-red">Locked</span>@elseif ($c->is_active)<span class="badge-teal">Active</span>@else<span class="badge-gray">Disabled</span>@endif</td>
                    <td class="text-xs">@date($c->created_at)</td>
                    <td><div class="flex flex-wrap justify-end gap-1">
                        @include('partials.generate-form', ['action' => route('app.customers.generate', $c), 'name' => $c->name])
                        <x-confirm :action="route('app.customers.toggle', $c)" :message="$c->is_active ? 'Disable '.$c->name.'? They will not be able to sign in.' : 'Enable '.$c->name.'?'">{{ $c->is_active ? 'Disable' : 'Enable' }}</x-confirm>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty title="No customers found" icon="users" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-4">{{ $customers->links() }}</div>
    </div>
</x-layouts.workspace>
