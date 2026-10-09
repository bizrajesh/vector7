<x-layouts.workspace :title="'Check plots — '.$project->name">
    @php($cols = array_keys(\App\Services\PlotImporter::COLUMNS))
    <x-page-header title="Check plots before import" :subtitle="$project->name.' · '.count($rows).' row(s)'" :back="route('ws.launch.show', $project)" />
    @if ($note)<div class="flash-ok mb-4">{{ $note }}</div>@endif
    @if ($errorCount = count($rowErrors))
        <div class="flash-err mb-4" role="alert"><div>
            <p class="font-bold">{{ $errorCount }} row(s) have errors. Nothing has been imported. Fix them in the grid below and press “Check again & import”.</p>
            <ul class="mt-2 max-h-48 list-inside list-disc overflow-y-auto text-xs">@foreach ($rowErrors as $line => $msgs)<li>Row {{ $line }}: {{ implode('; ', $msgs) }}</li>@endforeach</ul>
        </div></div>
    @else
        <div class="flash-ok mb-4">All {{ count($rows) }} rows are valid. Review and press “Import plots”.</div>
    @endif
    @foreach ($warnings as $w)<div class="flash-warn mb-4">{{ $w }}</div>@endforeach

    <form method="POST" action="{{ route('ws.launch.import.confirm', $project) }}" data-grid-form>
        @csrf
        <input type="hidden" name="rows_json" value="">
        <div class="card"><div class="table-wrap max-h-[65vh] overflow-y-auto"><table class="tbl text-xs">
            <thead class="sticky top-0 z-10"><tr><th>#</th>@foreach ($cols as $c)<th>{{ $c }}{{ \App\Services\PlotImporter::COLUMNS[$c] === 'Yes' ? ' *' : '' }}</th>@endforeach<th></th></tr></thead>
            <tbody>
            @foreach ($rows as $i => $r)
                @php($line = $r['_row'] ?? $i + 1)
                <tr data-grid-row class="{{ isset($rowErrors[$line]) ? 'bg-red-50' : '' }}">
                    <td class="font-semibold {{ isset($rowErrors[$line]) ? 'text-red-700' : '' }}" @if (isset($rowErrors[$line])) title="{{ implode('; ', $rowErrors[$line]) }}" @endif>{{ $line }}</td>
                    @foreach ($cols as $c)
                        <td>
                            @if ($c === 'facing')
                                <select class="input min-w-[7.5rem] py-1 text-xs" data-col="facing" aria-label="Row {{ $line }} facing"><option value=""></option>@foreach (\App\Models\Plot::FACINGS as $f)<option @selected(strcasecmp($r['facing'] ?? '', $f) === 0)>{{ $f }}</option>@endforeach</select>
                            @elseif ($c === 'status')
                                <select class="input py-1 text-xs" data-col="status" aria-label="Row {{ $line }} status">@foreach (['Available', 'Blocked'] as $s)<option @selected(strcasecmp($r['status'] ?? '', $s) === 0)>{{ $s }}</option>@endforeach</select>
                            @elseif ($c === 'corner_plot')
                                <select class="input py-1 text-xs" data-col="corner_plot" aria-label="Row {{ $line }} corner">@foreach (['N', 'Y'] as $s)<option @selected(strtoupper(substr($r['corner_plot'] ?? 'N', 0, 1)) === $s)>{{ $s }}</option>@endforeach</select>
                            @else
                                <input class="input py-1 text-xs {{ in_array($c, ['plot_no', 'size_sqft', 'length_ft', 'width_ft', 'road_width_ft', 'rate_per_sqft', 'offer_rate_per_sqft']) ? 'w-24' : 'min-w-[8rem]' }}" data-col="{{ $c }}" value="{{ $r[$c] ?? '' }}" aria-label="Row {{ $line }} {{ $c }}" @if ($c === 'offer_valid_till') placeholder="DD-MM-YYYY" @endif>
                            @endif
                        </td>
                    @endforeach
                    <td><input type="hidden" data-col="map_polygon" value="{{ isset($r['map_polygon']) && $r['map_polygon'] ? json_encode($r['map_polygon']) : '' }}"><button type="button" class="btn-ghost btn-sm" data-grid-remove aria-label="Remove row {{ $line }}">✕</button></td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
        <div class="mt-4 flex gap-2">
            <button class="btn-primary">{{ count($rowErrors) ? 'Check again & import' : 'Import plots' }}</button>
            <a href="{{ route('ws.launch.show', $project) }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</x-layouts.workspace>
