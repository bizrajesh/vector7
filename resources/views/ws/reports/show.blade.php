<x-layouts.workspace :title="$title">
    <x-page-header :title="$title" :subtitle="$desc">
        <a href="{{ route('ws.reports.index') }}" class="btn-ghost"><x-icon name="chevron-left" class="h-4 w-4" /> All reports</a>
        @can('reports.export')<x-export-buttons :pdf="true" />@endcan
    </x-page-header>
    @if ($filters)
        <x-filters>
            @if (in_array('project', $filters))<x-select name="project" label="Project" :options="$projects" :value="request('project')" placeholder="All" />@endif
            @if (in_array('status', $filters))<x-select name="status" label="Status" :options="$statuses" :value="request('status')" placeholder="All" />@endif
            @if (in_array('from', $filters))<x-field name="from" type="date" label="From" :value="request('from')" />@endif
            @if (in_array('to', $filters))<x-field name="to" type="date" label="To" :value="request('to')" />@endif
            @if (in_array('overdue', $filters))<x-select name="overdue" label="Show" :options="['1' => 'Overdue only']" :value="request('overdue')" placeholder="All dues" />@endif
        </x-filters>
    @endif
    <div class="card">
        <p class="p-4 text-sm text-muted">{{ count($report['rows']) }} row(s){{ $sub ? ' · '.$sub : '' }}</p>
        <div class="table-wrap"><table class="tbl">
            <thead><tr>@foreach ($report['headers'] as $i => $h)<th class="{{ in_array($i, $report['money']) ? 'num' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($report['rows'] as $row)
                <tr>@foreach (array_values($row) as $i => $v)<td class="{{ in_array($i, $report['money']) || is_int($v) ? 'num' : '' }}">{{ \App\Services\ReportService::cell($report, $i, $v) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['headers']) }}"><x-empty title="No records match these filters" icon="chart" /></td></tr>
            @endforelse
            </tbody>
            @if ($report['totals'] && count($report['rows']))
                <tfoot class="border-t-2 border-navy font-bold"><tr>@foreach ($report['totals'] as $i => $v)<td class="px-3 py-2.5 {{ in_array($i, $report['money']) || is_int($v) ? 'text-right tabular-nums' : '' }}">{{ \App\Services\ReportService::cell($report, $i, $v) }}</td>@endforeach</tr></tfoot>
            @endif
        </table></div>
    </div>
</x-layouts.workspace>
