@extends('pdf.layout')
@section('content')
<table>
    <thead><tr>@foreach ($report['headers'] as $i => $h)<th class="{{ in_array($i, $report['money']) ? 'right' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($report['rows'] as $row)
        <tr>@foreach (array_values($row) as $i => $v)<td class="{{ in_array($i, $report['money']) || is_int($v) ? 'right' : '' }}">{{ \App\Services\ReportService::cell($report, $i, $v) }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($report['headers']) }}" class="center muted">No records.</td></tr>
    @endforelse
    @if ($report['totals'] && count($report['rows']))
        <tr class="total">@foreach ($report['totals'] as $i => $v)<td class="{{ in_array($i, $report['money']) || is_int($v) ? 'right' : '' }}">{{ \App\Services\ReportService::cell($report, $i, $v) }}</td>@endforeach</tr>
    @endif
    </tbody>
</table>
@endsection
