<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\IdGenerator;
use App\Services\PlanLimiter;
use App\Services\ProjectService;
use App\Support\Excel;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Projects: create, read, update, soft delete; Go / No-Go; start. */
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $q = Project::with('manager', 'estimate')->latest('id')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('project_code', 'like', "%$s%")->orWhere('location', 'like', "%$s%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('type'), fn ($q, $t) => $q->where('approval_type', $t))
            ->when($request->query('location'), fn ($q, $l) => $q->where('location', $l));
        if ($request->query('export') === 'xlsx' && $request->user()->hasPerm('projects.export')) {
            return Excel::download('projects.xlsx', ['Code', 'Name', 'Type', 'Location', 'District', 'Acres', 'Status', 'Progress %', 'Budget', 'Start', 'Est. end', 'Manager'],
                $q->get()->map(fn ($p) => [$p->project_code, $p->name, $p->approval_type, $p->location, $p->district, (float) $p->size_acres, $p->statusLabel(), (float) $p->progress_pct, (float) ($p->estimate?->total_cost ?? 0), $p->start_date, $p->est_end_date, $p->manager?->name]), 'Projects');
        }

        return view('ws.projects.index', [
            'projects' => $q->paginate(20)->withQueryString(),
            'locations' => Project::distinct()->orderBy('location')->pluck('location'),
            'counts' => Project::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create()
    {
        return view('ws.projects.form', ['project' => new Project(['approval_type' => 'Village', 'state' => 'Tamil Nadu']), 'managers' => $this->managers()]);
    }

    public function store(Request $request)
    {
        $tenant = app(Tenancy::class)->get();
        PlanLimiter::ensure($tenant, 'projects');
        $data = $this->validated($request);
        $project = Project::create($data + [
            'tenant_id' => $tenant->id,
            'project_code' => IdGenerator::next($tenant->id, 'project'),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('ws.projects.show', $project)->with('ok', "Project {$project->project_code} created. Next: prepare the estimate.");
    }

    public function show(Project $project)
    {
        $project->load(['estimate.lines', 'stages', 'manager', 'decisionMaker']);

        return view('ws.projects.show', [
            'project' => $project,
            'gaps' => $project->status === 'in_progress' ? ProjectService::readinessGaps($project) : [],
            'spend' => ProjectService::projectSpend($project),
            'plotStats' => $project->plots()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function edit(Project $project)
    {
        abort_if($project->isReadOnly(), 403, 'Closed projects are read-only.');

        return view('ws.projects.form', ['project' => $project, 'managers' => $this->managers()]);
    }

    public function update(Request $request, Project $project)
    {
        abort_if($project->isReadOnly(), 403, 'Closed projects are read-only.');
        $data = $this->validated($request, $project);
        if (in_array($project->status, ['in_progress', 'ready_to_launch', 'launched'], true)) {
            unset($data['approval_type']); // stages are already created for this approval type
        }
        $project->update($data + ['updated_by' => $request->user()->id]);

        return redirect()->route('ws.projects.show', $project)->with('ok', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        abort_if($project->status === 'launched' && $project->plots()->whereNotIn('status', ['available', 'blocked'])->exists(), 422, 'This project has bookings or sales and cannot be deleted.');
        $project->delete();

        return redirect()->route('ws.projects.index')->with('ok', 'Project deleted (it no longer counts towards your plan).');
    }

    public function decision(Request $request, Project $project)
    {
        $data = $request->validate(['decision' => 'required|in:go,no_go', 'notes' => 'nullable|string|max:2000']);
        ProjectService::decide($project, $data['decision'], $data['notes'] ?? null, $request->user()->id);

        return back()->with('ok', $data['decision'] === 'go' ? 'Go decision recorded. Set the start date to begin tracking.' : 'No-Go recorded. The project is closed (read-only).');
    }

    public function start(Request $request, Project $project)
    {
        $data = $request->validate(['start_date' => 'required|date', 'est_end_date' => 'nullable|date|after:start_date']);
        ProjectService::start($project, Carbon::parse($data['start_date']), isset($data['est_end_date']) ? Carbon::parse($data['est_end_date']) : null);

        return redirect()->route('ws.tracking.show', $project)->with('ok', 'Project started. Stages and sub-tasks are now active with planned dates and budgets.');
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        $data = $request->validate([
            'approval_type' => ['required', Rule::in(Project::APPROVAL_TYPES)],
            'name' => 'required|string|max:150',
            'location' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'district' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pin' => ['nullable', 'regex:/^\d{6}$/'],
            'email' => 'nullable|email|max:255',
            'contact' => ['nullable', 'regex:/^\d{10}$/'],
            'owner_name' => 'nullable|string|max:150',
            'survey_numbers' => 'nullable|string|max:255',
            'patta_numbers' => 'nullable|string|max:255',
            'size_acres' => 'required|numeric|min:0.01|max:100000',
            'land_classification' => ['nullable', Rule::in(Project::LAND_CLASSES)],
            'guideline_rate' => 'nullable|numeric|min:0',
            'market_rate' => 'nullable|numeric|min:0',
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', app(Tenancy::class)->id())],
            'map_url' => 'nullable|url|max:500',
        ]);
        $sqft = round((float) $data['size_acres'] * Project::SQFT_PER_ACRE, 2);
        $data['guideline_rate'] = (float) ($data['guideline_rate'] ?? 0);
        $data['market_rate'] = (float) ($data['market_rate'] ?? 0);
        $data['guideline_value'] = round($sqft * $data['guideline_rate'], 2);
        $data['market_value'] = round($sqft * $data['market_rate'], 2);

        return $data;
    }

    private function managers()
    {
        return User::where('is_active', true)->whereHas('role', fn ($q) => $q->whereIn('base_role', ['tenant_admin', 'tenant_manager']))->orderBy('name')->pluck('name', 'id');
    }
}
