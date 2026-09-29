<?php

namespace App\Http\Controllers\App;

use App\Enums\LayoutStatus;
use App\Enums\PlotStatus;
use App\Http\Controllers\Controller;
use App\Models\Layout;
use App\Models\Plot;
use App\Services\PlanLimits;
use App\Services\PlotImporter;
use App\Services\PlotStatusMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Launch (section 6.1–6.2): plot onboarding and the plot catalogue. */
class PlotController extends Controller
{
    public function index(Request $request, Layout $layout): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(PlotStatus::class)],
            'facing' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:20'],
        ]);

        $plots = $layout->plots()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->facing, fn ($q, $f) => $q->where('facing', $f))
            ->when($request->q, fn ($q, $term) => $q->where('plot_no', 'like', addcslashes($term, '%_').'%'))
            ->orderByRaw('LENGTH(plot_no), plot_no')->get();

        return view('app.plots.index', [
            'layout' => $layout,
            'plots' => $plots,
            'counts' => $layout->plots()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all(),
        ]);
    }

    public function show(Plot $plot): View
    {
        return view('app.plots.show', [
            'plot' => $plot->load(['layout', 'activeBooking.customer', 'activeSale.customer', 'activeSale.registration', 'history.user']),
        ]);
    }

    public function importForm(Layout $layout): View
    {
        $this->ensureImportable($layout);

        return view('app.plots.import', ['layout' => $layout, 'preview' => null]);
    }

    public function importPreview(Request $request, Layout $layout, PlotImporter $importer): View
    {
        $this->ensureImportable($layout);
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt']]);

        $result = $importer->parse($request->file('file'), $layout);
        // The validated rows are kept server-side in the session; the browser never re-posts row data.
        $request->session()->put('plot_import.'.$layout->id, $result['rows']);

        return view('app.plots.import', ['layout' => $layout, 'preview' => $result]);
    }

    public function importCommit(Request $request, Layout $layout, PlotImporter $importer): RedirectResponse
    {
        $this->ensureImportable($layout);
        $rows = $request->session()->pull('plot_import.'.$layout->id, []);
        abort_if($rows === [], 422, 'Nothing to import. Upload the file again.');

        $count = $importer->commit($layout, $rows);

        return $this->done("{$count} plots imported.", 'app.plots.index', $layout);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'wb');
            fputcsv($out, PlotImporter::COLUMNS);
            fputcsv($out, ['GM-01', '142/3B', '1200', '1450', '', 'available', 'east', '30x40', '30 ft road', 'GM-06', 'GM-02', 'Park']);
            fclose($out);
        }, 'vector7-plot-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request, Layout $layout, PlanLimits $limits): RedirectResponse
    {
        $this->ensureImportable($layout);
        $limits->ensureCanAddPlots(1);

        $data = $this->validated($request, $layout);
        $plot = new Plot($data + ['cost' => $data['cost'] ?? round($data['size_sqft'] * $data['rate_sqft'], 2)]);
        $plot->layout_id = $layout->id;
        $plot->status = PlotStatus::Available;
        $plot->save();

        return $this->done("Plot {$plot->plot_no} added.");
    }

    public function update(Request $request, Plot $plot): RedirectResponse
    {
        abort_unless(in_array($plot->status, [PlotStatus::Available, PlotStatus::Reserved], true), 422, 'Plots in a booking or sale cannot be edited.');

        $data = $this->validated($request, $plot->layout, $plot);
        $plot->update($data + ['cost' => $data['cost'] ?? round($data['size_sqft'] * $data['rate_sqft'], 2)]);

        return $this->done('Plot updated.');
    }

    public function toggleReserve(Request $request, Plot $plot, PlotStatusMachine $machine): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];

        DB::transaction(function () use ($plot, $machine, $reason) {
            $plot = Plot::query()->whereKey($plot->id)->lockForUpdate()->firstOrFail();
            $machine->transition($plot, $plot->status === PlotStatus::Reserved ? PlotStatus::Available : PlotStatus::Reserved, $reason);
        });

        return $this->done('Plot status updated.');
    }

    private function validated(Request $request, Layout $layout, ?Plot $plot = null): array
    {
        return $request->validate([
            'plot_no' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('plots', 'plot_no')->where('layout_id', $layout->id)->ignore($plot)],
            'survey_no' => ['nullable', 'string', 'max:40'],
            'size_sqft' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'rate_sqft' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'facing' => ['nullable', Rule::in(['north', 'south', 'east', 'west', 'north_east', 'north_west', 'south_east', 'south_west'])],
            'dimensions' => ['nullable', 'string', 'max:40'],
            'boundary_north' => ['nullable', 'string', 'max:120'],
            'boundary_south' => ['nullable', 'string', 'max:120'],
            'boundary_east' => ['nullable', 'string', 'max:120'],
            'boundary_west' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function ensureImportable(Layout $layout): void
    {
        abort_unless(in_array($layout->status, [LayoutStatus::ReadyToLaunch, LayoutStatus::Launched], true), 422, 'Plots can be onboarded once every stage is complete (Ready to Launch).');
    }
}
