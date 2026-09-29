<x-layouts.app :title="'Import plots · '.$layout->name">
    <x-page-header title="Import plots" :subtitle="$layout->name" :back="route('app.plots.index', $layout)" />
    <div class="grid gap-4 lg:grid-cols-3">
        <form method="POST" action="{{ route('app.plots.import.preview', $layout) }}" enctype="multipart/form-data" class="card-pad flex flex-col gap-3">@csrf
            <h2 class="section-title">1. Upload CSV</h2>
            <p class="text-sm text-ink-2">Columns: plot_no, survey_no, size_sqft, rate_sqft, plot_cost, status (available / reserved), facing, dimensions and four boundaries. Plot cost is calculated when blank.</p>
            <a href="{{ route('app.plots.template') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold"><x-icon name="download" class="h-4 w-4" />Download template</a>
            <div><label for="csv" class="label">CSV file (max 2 MB, 2,000 rows)</label><input id="csv" type="file" name="file" accept=".csv,text/csv" required class="input py-2.5"></div>
            <button class="btn-primary">Validate file</button>
        </form>
        <div class="card-pad lg:col-span-2">
            <h2 class="section-title mb-2">2. Review and import</h2>
            @if (! $preview)
                <p class="text-sm text-ink-muted">Upload a file to see a preview. Nothing is saved until you confirm.</p>
            @else
                <p class="text-sm"><span class="badge-av">{{ count($preview['rows']) }} valid</span> <span class="badge-od">{{ count($preview['errors']) }} with errors</span></p>
                @if ($preview['errors'])
                    <ul class="mt-3 max-h-48 overflow-y-auto rounded-xl bg-red-50 p-3 text-sm text-red-800">
                        @foreach ($preview['errors'] as $line => $message)<li>Row {{ $line }}: {{ $message }}</li>@endforeach
                    </ul>
                @endif
                @if ($preview['rows'])
                    <div class="mt-3 max-h-80 overflow-auto">
                        <table class="table min-w-[560px]">
                            <thead><tr><th>Plot</th><th>Survey</th><th>Size</th><th>Rate</th><th>Cost</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach (array_slice($preview['rows'], 0, 200) as $row)
                                <tr><td class="font-semibold">{{ $row['plot_no'] }}</td><td>{{ $row['survey_no'] }}</td><td class="num">{{ $row['size_sqft'] }}</td><td class="num">{{ $row['rate_sqft'] }}</td>
                                    <td class="num">@inr($row['cost']) @if ($row['cost_mismatch'])<span class="badge-bk">≠ size×rate</span>@endif</td><td>{{ ucfirst($row['status']) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <form method="POST" action="{{ route('app.plots.import.commit', $layout) }}" class="mt-4" data-confirm="Import {{ count($preview['rows']) }} plots?">@csrf
                        <button class="btn-primary">Import {{ count($preview['rows']) }} valid plots</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-layouts.app>
