@if ($errs = session('import_errors'))
    <div class="card card-pad mb-6 ring-1 ring-red-200">
        <h2 class="section-title text-red-800">Import errors ({{ count($errs) }})</h2>
        <p class="text-sm text-muted">Nothing was imported. Correct these rows in the file and upload it again.</p>
        <div class="table-wrap mt-3 max-h-96 overflow-y-auto">
            <table class="tbl"><thead><tr><th>Sheet</th><th>Row</th><th>Problem</th></tr></thead><tbody>
                @foreach ($errs as $e)<tr><td class="whitespace-nowrap">{{ $e['sheet'] ?? '' }}</td><td>{{ $e['row'] }}</td><td>{{ $e['message'] }}</td></tr>@endforeach
            </tbody></table>
        </div>
    </div>
@endif
@if ($w = session('warnings'))
    @foreach ($w as $msg)<div class="flash-warn mb-3">{{ $msg }}</div>@endforeach
@endif
