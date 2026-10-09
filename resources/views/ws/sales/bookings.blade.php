<x-layouts.workspace title="Bookings">
    <x-page-header title="Bookings" subtitle="A booking holds the plot for the booking validity period (working days). Unpaid bookings are released automatically.">
        @can('bookings.export')<x-export-buttons />@endcan
        @can('bookings.create')<a href="{{ route('ws.bookings.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> New booking</a>@endcan
    </x-page-header>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Booking no., customer, mobile" />
        <x-select name="project" label="Project" :options="$projects" :value="request('project')" placeholder="All" />
        <x-select name="location" label="Location" :options="$locations->combine($locations)" :value="request('location')" placeholder="All" />
        <x-select name="status" label="Status" :options="\App\Models\Booking::STATUSES" :value="request('status')" placeholder="All" />
    </x-filters>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Booking</th><th>Plot</th><th>Customer</th><th class="num">Net price</th><th>Valid till</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($bookings as $b)
            @php($soon = $b->status === 'active' && $b->valid_till->lte(today()->addDay()))
            <tr>
                <td><a href="{{ route('ws.bookings.show', $b) }}" class="font-mono text-xs font-semibold">{{ $b->booking_no }}</a><p class="text-xs text-muted">@date($b->booked_on) · {{ $b->source }}</p></td>
                <td><p class="font-semibold">Plot {{ $b->plot->plot_no }}</p><p class="text-xs text-muted">{{ $b->project->name }}</p></td>
                <td><p>{{ $b->customer->name }}</p><p class="text-xs text-muted">{{ $b->customer->mobile }}</p></td>
                <td class="num">@inr($b->net_price)</td>
                <td class="{{ $soon ? 'font-bold text-red-700' : '' }}">@date($b->valid_till)</td>
                <td><span class="{{ ['active' => 'badge-amber', 'converted' => 'badge-teal', 'expired' => 'badge-gray', 'cancelled' => 'badge-gray'][$b->status] }}">{{ \App\Models\Booking::STATUSES[$b->status] }}</span></td>
                <td class="whitespace-nowrap text-right">@if ($b->status === 'active')@can('sales.create')<a href="{{ route('ws.sales.create', ['booking' => $b->id]) }}" class="btn-teal btn-sm">Start sale</a>@endcan @endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="No bookings" icon="calendar" /></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $bookings->links() }}</div></div>
</x-layouts.workspace>
