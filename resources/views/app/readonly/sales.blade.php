<x-layouts.workspace title="Bookings & sales">
    <x-page-header title="Bookings & sales — all tenants" subtitle="Live, read-only view across every promoter.">
        <x-export-buttons />
    </x-page-header>
    <nav class="mb-4 flex gap-2" aria-label="View">
        <a href="{{ route('app.sales.index', array_merge(request()->except('tab', 'page', 'status'), ['tab' => 'sales'])) }}" class="{{ $tab === 'sales' ? 'btn-primary' : 'btn-light' }}" @if ($tab === 'sales') aria-current="page" @endif>Sales</a>
        <a href="{{ route('app.sales.index', array_merge(request()->except('tab', 'page', 'status'), ['tab' => 'bookings'])) }}" class="{{ $tab === 'bookings' ? 'btn-primary' : 'btn-light' }}" @if ($tab === 'bookings') aria-current="page" @endif>Bookings</a>
    </nav>
    <x-filters>
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-select name="tenant" label="Tenant" :options="$tenants" :value="request('tenant')" placeholder="All tenants" />
        <x-select name="project" label="Project" :options="$projects" :value="request('project')" placeholder="All" />
        <x-select name="location" label="Location" :options="$locations->combine($locations)" :value="request('location')" placeholder="All" />
        <x-select name="status" label="Status" :options="$statuses" :value="request('status')" placeholder="All" />
        <x-select name="period" label="Timeline" :options="['month' => 'This month', 'quarter' => 'This quarter', 'year' => 'This financial year']" :value="request('period')" placeholder="Any time" />
    </x-filters>
    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat :label="$tab === 'sales' ? 'Sales' : 'Bookings'" :value="number_format($totals->n)" icon="cart" />
        <x-stat label="Value" :value="\App\Support\Format::inrShort($totals->value)" icon="rupee" tone="gold" />
        @if ($tab === 'sales')
            <x-stat label="Collected" :value="\App\Support\Format::inrShort($totals->paid)" icon="check" tone="navy" />
            <x-stat label="Outstanding" :value="\App\Support\Format::inrShort($totals->due)" icon="clock" tone="red" />
        @endif
    </div>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr>
            <th>{{ $tab === 'sales' ? 'Sale' : 'Booking' }}</th><th>Tenant</th><th>Plot</th><th>Customer</th><th class="num">Net price</th>
            @if ($tab === 'sales')<th class="num">Paid</th><th class="num">Due</th>@else<th>Valid till</th>@endif
            <th>Status</th>
        </tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td><span class="font-mono text-xs font-semibold">{{ $tab === 'sales' ? $r->sale_no : $r->booking_no }}</span><p class="text-xs text-muted">@date($tab === 'sales' ? $r->started_on : $r->booked_on)</p></td>
                <td>{{ $r->tenant?->name }}</td>
                <td><p class="font-semibold">Plot {{ $r->plot?->plot_no }}</p><p class="text-xs text-muted">{{ $r->project?->name }}, {{ $r->project?->location }}</p></td>
                <td>{{ $r->customer?->name }}<p class="text-xs text-muted">{{ $r->customer?->mobile }}</p></td>
                <td class="num">@inr($r->net_price)</td>
                @if ($tab === 'sales')<td class="num">@inr($r->paid_amount)</td><td class="num">@inr($r->due_amount)</td>@else<td>@date($r->valid_till)</td>@endif
                <td><span class="badge-navy">{{ $statuses[$r->status] ?? $r->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty title="Nothing matches these filters" icon="cart" /></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $rows->links() }}</div></div>
</x-layouts.workspace>
