<x-layouts.workspace title="Sales & payments">
    <x-page-header title="Sales & payments" subtitle="Available → Booked → Sale Init → ROR → ROR-Init → ROR-Completed → Sold">
        <a href="{{ route('ws.brokers.index') }}" class="btn-light">Brokers</a>
        @can('sales.export')<x-export-buttons />@endcan
        @can('sales.create')<a href="{{ route('ws.sales.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Direct sale</a>@endcan
    </x-page-header>
    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <x-stat label="Sales value" :value="\App\Support\Format::inrShort($totals['value'])" icon="cart" />
        <x-stat label="Collected" :value="\App\Support\Format::inrShort($totals['paid'])" icon="rupee" tone="navy" />
        <x-stat label="Due" :value="\App\Support\Format::inrShort($totals['due'])" icon="clock" tone="gold" />
    </div>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Sale no., customer, mobile" />
        <x-select name="project" label="Project" :options="$projects" :value="request('project')" placeholder="All" />
        <x-select name="location" label="Location" :options="$locations->combine($locations)" :value="request('location')" placeholder="All" />
        <x-select name="status" label="Status" :options="\App\Models\Sale::STATUSES" :value="request('status')" placeholder="All" />
    </x-filters>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Sale</th><th>Plot</th><th>Customer</th><th class="num">Net price</th><th class="w-40">Paid</th><th>Next due</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($sales as $s)
            @php($next = $s->nextDue())
            <tr>
                <td><a href="{{ route('ws.sales.show', $s) }}" class="font-mono text-xs font-semibold">{{ $s->sale_no }}</a><p class="text-xs text-muted">@date($s->started_on)</p></td>
                <td><p class="font-semibold">Plot {{ $s->plot->plot_no }}</p><p class="text-xs text-muted">{{ $s->project->name }}</p></td>
                <td>{{ $s->customer->name }}<p class="text-xs text-muted">{{ $s->customer->mobile }}</p></td>
                <td class="num">@inr($s->net_price)</td>
                <td><x-progress :pct="$s->net_price > 0 ? $s->paid_amount / $s->net_price * 100 : 0" level="teal" label="Paid" /><p class="mt-1 text-xs">@inr($s->paid_amount)</p></td>
                <td class="text-sm {{ $next && $next->due_date->isPast() && $s->status === 'sale_init' ? 'font-bold text-red-700' : '' }}">{{ $next && $s->status === 'sale_init' ? \App\Support\Format::inr($next->balance()).' · '.\App\Support\Format::date($next->due_date) : '—' }}</td>
                <td><x-status :status="$s->plot->status" /></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="No sales yet" icon="cart" /></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $sales->links() }}</div></div>
</x-layouts.workspace>
