<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectService;
use App\Support\Excel;
use App\Support\Pdf;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** Estimate & budget: facilities (tier) + approval stages + other costs; MRP; critical-path duration. */
class EstimateController extends Controller
{
    public function edit(Request $request, Project $project)
    {
        abort_if($project->isReadOnly(), 403, 'Closed projects are read-only.');
        if ($project->status === 'draft' && $request->user()->hasPerm('estimates.update')) {
            ProjectService::setStatus($project, 'init', 'Estimate started.');
        }
        $estimate = $project->estimate()->with('lines')->first();
        $tier = $request->query('tier', $estimate->tier ?? 'Basic');
        $tier = in_array($tier, ['Basic', 'Standard', 'Premium'], true) ? $tier : 'Basic';
        $settings = app(Tenancy::class)->get()->setting();

        return view('ws.projects.estimate', [
            'project' => $project,
            'estimate' => $estimate,
            'tier' => $tier,
            'facilities' => ProjectService::facilitiesFor($tier),
            'stages' => ProjectService::stageMasters($project),
            'settings' => $settings,
            'duration' => ProjectService::criticalPath(ProjectService::masterTaskGraph($project))['total'],
            'actualSellable' => (float) $project->plots()->sum('size_sqft'),
            'locked' => in_array($project->status, ['in_progress', 'ready_to_launch', 'launched'], true) || ! $request->user()->hasPerm('estimates.update'),
        ]);
    }

    public function save(Request $request, Project $project)
    {
        abort_if($project->isReadOnly(), 403);
        $data = $request->validate([
            'tier' => 'required|in:Basic,Standard,Premium',
            'sellable_pct' => 'required|numeric|min:1|max:100',
            'mrp_multiplier' => 'required|numeric|min:0.1|max:20',
            'facility' => 'array',
            'facility.*.include' => 'nullable',
            'facility.*.qty' => 'nullable|numeric|min:0',
            'facility.*.cost' => 'nullable|numeric|min:0',
            'stage' => 'array',
            'stage.*.name' => 'required|string|max:255',
            'stage.*.cost' => 'nullable|numeric|min:0',
            'stage.*.include' => 'nullable',
            'other' => 'array',
            'other.*.description' => 'nullable|string|max:255',
            'other.*.qty' => 'nullable|numeric|min:0',
            'other.*.cost' => 'nullable|numeric|min:0',
            'other.*.unit' => 'nullable|string|max:30',
        ]);
        if (in_array($project->status, ['in_progress', 'ready_to_launch', 'launched'], true)) {
            return back()->with('error', 'The estimate is locked after the project starts. Track spending under Accounts → Expenses.');
        }
        ProjectService::saveEstimate($project, $data, app(Tenancy::class)->get());

        return redirect()->route('ws.projects.show', $project)->with('ok', 'Estimate saved. Record the Go / No-Go decision next.');
    }

    public function export(Project $project, string $format)
    {
        $estimate = $project->estimate()->with('lines')->firstOrFail();
        $name = 'estimate-'.$project->project_code;
        if ($format === 'pdf') {
            return Pdf::download('pdf.estimate', ['project' => $project, 'estimate' => $estimate, 'tenant' => app(Tenancy::class)->get()], $name.'.pdf');
        }
        $rows = $estimate->lines->map(fn ($l) => [ucfirst($l->line_type), $l->description, $l->unit, (float) $l->quantity, (float) $l->unit_cost, (float) $l->amount])->all();
        $rows[] = ['', '', '', '', 'Total production cost', (float) $estimate->total_cost];
        $rows[] = ['', '', '', '', 'Sellable area (sq ft)', (float) $estimate->sellable_sqft];
        $rows[] = ['', '', '', '', 'Cost per sellable sq ft', (float) $estimate->cost_per_sqft];
        $rows[] = ['', '', '', '', 'MRP per sq ft (× '.(float) $estimate->mrp_multiplier.')', (float) $estimate->mrp_per_sqft];
        $rows[] = ['', '', '', '', 'Estimated duration (working days)', $estimate->est_duration_days];

        return Excel::download($name.'.xlsx', ['Type', 'Item', 'Unit', 'Quantity', 'Unit cost', 'Amount'], $rows, "Estimate — {$project->name} ({$project->project_code}), {$estimate->tier} tier");
    }
}
