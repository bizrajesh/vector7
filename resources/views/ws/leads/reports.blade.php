<x-layouts.workspace title="Lead reports">
    <x-page-header title="Lead reports" :subtitle="'Leads created '.\App\Support\Format::date($from).' – '.\App\Support\Format::date($to)" :back="route('ws.leads.index')">
        @can('leads.export')<x-export-buttons />@endcan
    </x-page-header>
    <x-filters>
        <x-field name="from" type="date" label="From" :value="$from->format('Y-m-d')" />
        <x-field name="to" type="date" label="To" :value="$to->format('Y-m-d')" />
    </x-filters>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Leads" :value="number_format($summary['total'])" icon="chat" />
        <x-stat label="Won" :value="number_format($summary['won'])" icon="check" tone="navy" :sub="$summary['lost'].' lost'" />
        <x-stat label="Conversion rate" :value="$summary['conversion'].'%'" icon="chart" tone="gold" />
        <x-stat label="Avg. time to first contact" :value="$summary['first_contact_h'] !== null ? $summary['first_contact_h'].' h' : '—'" icon="clock" />
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card card-pad"><h2 class="font-bold">Leads by source</h2><x-chart id="ch-src" :config="$sourceChart" label="Leads by source" /></div>
        <div class="card card-pad"><h2 class="font-bold">Leads by stage</h2><x-chart id="ch-stage" :config="$stageChart" label="Leads by stage" /></div>
        <div class="card card-pad"><h2 class="font-bold">Top listings & layouts</h2><x-chart id="ch-listing" :config="$listingChart" label="Leads by listing or layout" /></div>
        <div class="card">
            <h2 class="section-title p-4">Lost reasons</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Reason</th><th class="num">Leads</th></tr></thead>
                <tbody>@forelse ($lostReasons as $r => $n)<tr><td>{{ $r }}</td><td class="num">{{ $n }}</td></tr>@empty<tr><td colspan="2" class="text-sm text-muted">No lost leads in this period.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
    </div>
    <div class="card mt-6">
        <h2 class="section-title p-4">Sales user performance</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>User</th><th class="num">Leads</th><th class="num">Won</th><th class="num">Lost</th><th class="num">Conversion</th><th class="num">Avg. first contact</th></tr></thead>
            <tbody>
            @forelse ($perf as $name => $p)
                <tr><td class="font-semibold">{{ $name }}</td><td class="num">{{ $p['leads'] }}</td><td class="num">{{ $p['won'] }}</td><td class="num">{{ $p['lost'] }}</td>
                    <td class="num">{{ $p['leads'] ? round($p['won'] / $p['leads'] * 100, 1) : 0 }}%</td><td class="num">{{ $p['first_contact_h'] !== null ? $p['first_contact_h'].' h' : '—' }}</td></tr>
            @empty
                <tr><td colspan="6"><x-empty title="No leads in this period" icon="chart" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</x-layouts.workspace>
