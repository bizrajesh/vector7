<x-layouts.workspace title="Customers">
    <x-page-header title="Customers" subtitle="Buyers who have booked or bought a plot from you. Read-only — buyers manage their own profile.">
        @can('tenant_customers.export')<x-export-buttons />@endcan
    </x-page-header>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Name, email, mobile" />
    </x-filters>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Customer</th><th>Mobile</th><th>City</th><th class="num">Bookings</th><th class="num">Purchases</th><th>Since</th></tr></thead>
        <tbody>
        @forelse ($customers as $c)
            <tr>
                <td><a href="{{ route('ws.customers.show', $c->id) }}" class="font-semibold">{{ $c->name }}</a><p class="text-xs text-muted">{{ $c->email }}</p></td>
                <td>{{ $c->mobile }}</td>
                <td>{{ $c->city ?: '—' }}</td>
                <td class="num">{{ $c->bookings_count }}</td>
                <td class="num">{{ $c->sales_count }}</td>
                <td class="text-xs">@date($c->pivot->created_at)</td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty title="No customers yet" icon="users">Customers appear here after their first booking.</x-empty></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $customers->links() }}</div></div>
</x-layouts.workspace>
