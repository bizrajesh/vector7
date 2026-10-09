<x-layouts.workspace title="Receivables">
    <x-page-header title="Receivables" subtitle="Unpaid instalments on running sales, earliest due first.">
        @can('accounts.export')<x-export-buttons />@endcan
    </x-page-header>
    @include('ws.accounts._nav')
    <x-filters>
        <x-select name="project" label="Project" :options="$projects" :value="request('project')" placeholder="All" />
        <x-select name="overdue" label="Show" :options="['1' => 'Overdue only']" :value="request('overdue')" placeholder="All dues" />
    </x-filters>
    @php($od = $rows->filter(fn ($i) => $i->due_date->lt(today())))
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-stat label="Total due" :value="\App\Support\Format::inr($rows->sum(fn ($i) => $i->balance()))" icon="clock" />
        <x-stat label="Overdue" :value="\App\Support\Format::inr($od->sum(fn ($i) => $i->balance()))" icon="alert" :tone="$od->isNotEmpty() ? 'red' : 'teal'" />
        <x-stat label="Buyers with dues" :value="$rows->pluck('sale.customer_id')->unique()->count()" icon="users" tone="navy" />
    </div>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Due date</th><th>Sale</th><th>Customer</th><th>Instalment</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Balance</th></tr></thead>
        <tbody>
        @forelse ($rows as $i)
            @php($late = $i->due_date->lt(today()))
            <tr>
                <td class="whitespace-nowrap {{ $late ? 'font-bold text-red-700' : '' }}">@date($i->due_date)@if ($late)<p class="text-xs">{{ (int) $i->due_date->diffInDays(today()) }} days late</p>@endif</td>
                <td>@can('sales.view')<a href="{{ route('ws.sales.show', $i->sale) }}" class="font-mono text-xs font-semibold">{{ $i->sale->sale_no }}</a>@else<span class="font-mono text-xs">{{ $i->sale->sale_no }}</span>@endcan<p class="text-xs text-muted">Plot {{ $i->sale->plot->plot_no }} · {{ $i->sale->project->name }}</p></td>
                <td>{{ $i->sale->customer->name }}<p class="text-xs text-muted"><a href="tel:{{ $i->sale->customer->mobile }}">{{ $i->sale->customer->mobile }}</a></p></td>
                <td>{{ $i->name }}</td>
                <td class="num">@inr($i->amount)</td>
                <td class="num">@inr($i->paid_amount)</td>
                <td class="num font-semibold">@inr($i->balance())</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="No dues" icon="check">Every running sale is paid up.</x-empty></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.workspace>
