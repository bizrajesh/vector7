<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\PlotImporter;
use Illuminate\Http\Request;

/**
 * Plot onboarding: download the plot onboarding template (.xlsx), fill it, pick the project and upload.
 * The upload goes through the same preview → fix → confirm steps as Launch & plots (nothing is saved until every row is valid).
 */
class PlotOnboardingController extends Controller
{
    public const TEMPLATE = 'templates/vector7-plot-onboarding-template.xlsx';

    public function index(Request $request)
    {
        $projects = Project::whereIn('status', ['ready_to_launch', 'launched'])->withCount('plots')->orderBy('name')->get();

        return view('ws.plot-onboarding.index', [
            'projects' => $projects,
            'selected' => (int) $request->query('project'),
            'columns' => PlotImporter::COLUMNS,
            'others' => Project::whereIn('status', LaunchController::PHASE_STATUSES)->count(),
        ]);
    }

    public function template()
    {
        return response()->download(resource_path(self::TEMPLATE), 'vector7-plot-onboarding-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate(['project_id' => 'required|integer']);
        // Tenant scope applies: another promoter's project id is simply not found.
        $project = Project::whereIn('status', ['ready_to_launch', 'launched'])->findOrFail($request->integer('project_id'));
        if (! $request->hasFile('file')) {
            return back()->withInput()->with('error', 'Choose the filled plot onboarding file (.xlsx or .csv).');
        }

        return app(PlotImportController::class)->preview($request, $project);
    }
}
