<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Project;
use App\Services\PlotImporter;
use App\Support\Excel;
use Illuminate\Http\Request;

/** Per-plot edit (Admin / Manager), map pin placement, export. */
class PlotController extends Controller
{
    public function edit(Project $project, Plot $plot)
    {
        abort_unless($plot->project_id === $project->id, 404);

        return view('ws.launch.plot', ['project' => $project, 'plot' => $plot]);
    }

    public function update(Request $request, Project $project, Plot $plot)
    {
        abort_unless($plot->project_id === $project->id, 404);
        $row = $request->only(array_keys(PlotImporter::COLUMNS));
        $row['status'] = in_array($plot->status, ['available', 'blocked'], true) ? ($row['status'] ?? $plot->status) : 'available';
        $row['_row'] = 1;
        // validate this single row on its own (other plots are not re-checked for duplicates except plot_no clash)
        [$errors, , $clean] = PlotImporter::validate($project, [$row], [strtoupper($plot->plot_no), strtoupper((string) ($row['plot_no'] ?? ''))]);
        if (! $errors && strtoupper($row['plot_no'] ?? '') !== strtoupper($plot->plot_no) && Plot::where('project_id', $project->id)->where('plot_no', strtoupper($row['plot_no']))->exists()) {
            $errors = [1 => ['plot_no already exists in this project']];
        }
        if ($errors) {
            return back()->withInput()->with('error', implode('; ', $errors[1] ?? []));
        }
        $data = $clean[0];
        $status = $data['status'];
        unset($data['status'], $data['map_polygon']);
        $plot->update($data + ['updated_by' => $request->user()->id]);
        if (in_array($plot->status, ['available', 'blocked'], true)) {
            $plot->moveTo($status, 'Edited');
        }

        return redirect()->route('ws.launch.show', $project)->with('ok', "Plot {$plot->plot_no} saved.");
    }

    public function destroy(Project $project, Plot $plot)
    {
        abort_unless($plot->project_id === $project->id, 404);
        if (! in_array($plot->status, ['available', 'blocked'], true)) {
            return back()->with('error', 'Only Available or Blocked plots can be deleted.');
        }
        $plot->delete();

        return back()->with('ok', 'Plot deleted.');
    }

    /** Save the plot's position on the layout picture (percent coordinates). */
    public function pin(Request $request, Project $project, Plot $plot)
    {
        abort_unless($plot->project_id === $project->id, 404);
        $data = $request->validate(['x' => 'required|numeric|min:0|max:100', 'y' => 'required|numeric|min:0|max:100']);
        $plot->update(['map_x' => $data['x'], 'map_y' => $data['y']]);

        return response()->json(['ok' => true]);
    }

    public function export(Project $project)
    {
        $plots = $project->plots()->orderByRaw('CAST(plot_no AS UNSIGNED), plot_no')->get();

        return Excel::download('plots-'.$project->project_code.'.xlsx',
            array_merge(array_keys(PlotImporter::COLUMNS), ['actual_price', 'offer_price', 'current_status']),
            $plots->map(fn ($p) => [
                $p->plot_no, $p->patta_number, (float) $p->size_sqft, $p->length_ft ? (float) $p->length_ft : '', $p->width_ft ? (float) $p->width_ft : '', $p->facing,
                $p->east_boundary, $p->west_boundary, $p->north_boundary, $p->south_boundary, $p->road_width_ft ? (float) $p->road_width_ft : '', $p->is_corner ? 'Y' : 'N',
                (float) $p->rate_per_sqft, $p->offer_text, $p->offer_rate_per_sqft ? (float) $p->offer_rate_per_sqft : '', $p->offer_valid_till?->format('d-m-Y') ?? '',
                in_array($p->status, ['available', 'blocked'], true) ? ucfirst($p->status) : 'Available',
                $p->actualPrice(), $p->offerPrice() ?? '', $p->statusLabel(),
            ])->all(), 'Plots — '.$project->name);
    }
}
