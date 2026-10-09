<x-layouts.workspace title="Day book">
    <x-page-header title="Day book" subtitle="Receipts and payments in date order with a running balance.">
        @can('accounts.export')<x-export-buttons />@endcan
        <button type="button" class="btn-light" data-print><x-icon name="printer" class="h-4 w-4" /> Print</button>
    </x-page-header>
    @include('ws.accounts._nav')
    <x-filters>
        <x-field name="from" type="date" label="From" :value="$from->format('Y-m-d')" />
        <x-field name="to" type="date" label="To" :value="$to->format('Y-m-d')" />
    </x-filters>
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-stat label="Receipts" :value="\App\Support\Format::inr($rows->sum('in'))" icon="download" />
        <x-stat label="Payments" :value="\App\Support\Format::inr($rows->sum('out'))" icon="upload" tone="gold" />
        <x-stat label="Net for the period" :value="\App\Support\Format::inr($rows->sum('in') - $rows->sum('out'))" icon="rupee" tone="navy" />
    </div>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Date</th><th>Txn</th><th>Particulars</th><th>Mode</th><th class="num">Receipts</th><th class="num">Payments</th><th class="num">Balance</th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td class="whitespace-nowrap">@date($r['date'])</td>
                <td class="font-mono text-xs">{{ $r['txn'] }}</td>
                <td>{{ $r['particulars'] }}</td>
                <td>{{ $r['mode'] }}</td>
                <td class="num text-teal-700">{{ $r['in'] ? \App\Support\Format::inr($r['in']) : '' }}</td>
                <td class="num text-red-700">{{ $r['out'] ? \App\Support\Format::inr($r['out']) : '' }}</td>
                <td class="num font-semibold {{ $r['balance'] < 0 ? 'text-red-700' : '' }}">@inr($r['balance'])</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="Nothing in this period" icon="doc" /></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.workspace>
