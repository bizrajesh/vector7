<?php

namespace App\Services;

use App\Models\DocumentChecklist;
use App\Models\EstimateLine;
use App\Models\Expense;
use App\Models\FacilityMaster;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectEstimate;
use App\Models\ProjectStage;
use App\Models\ProjectSubtask;
use App\Models\StageMaster;
use App\Models\Tenant;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project lifecycle: Draft → Init → Go / No-Go → In Progress → Ready to Launch → Launched → Closed (No-Go → Closed).
 * Estimate & budget, critical-path duration, schedule in working days, progress roll-up and budget alerts.
 */
class ProjectService
{
    /** Stage masters (with active sub-tasks) for the project's approval type. */
    public static function stageMasters(Project $project)
    {
        return StageMaster::where('area_type', $project->approval_type)->where('is_active', true)->orderBy('stage_no')
            ->with(['subtasks' => fn ($q) => $q->where('is_active', true)->with('dependencies', 'documents')])->get();
    }

    /** Facilities offered for a tier: Y = included (pre-selected), Opt = optional, '-' = not offered. */
    public static function facilitiesFor(string $tier)
    {
        $col = 'tier_'.strtolower($tier);

        return FacilityMaster::where('is_active', true)->where($col, '!=', '-')->orderBy('sino')->get();
    }

    /**
     * Critical path over task dependencies (durations in working days).
     *
     * @param  array<string, array{duration:int, deps:array<int,string>}>  $tasks
     * @return array{total:int, es:array<string,int>, ef:array<string,int>}
     */
    public static function criticalPath(array $tasks): array
    {
        $es = [];
        $ef = [];
        $visiting = [];
        $calc = function (string $code) use (&$calc, &$es, &$ef, &$visiting, $tasks): int {
            if (isset($ef[$code])) {
                return $ef[$code];
            }
            if (isset($visiting[$code])) {
                throw ValidationException::withMessages(['estimate' => "Dependency loop at task $code."]);
            }
            $visiting[$code] = true;
            $start = 0;
            foreach ($tasks[$code]['deps'] as $d) {
                if (isset($tasks[$d])) {
                    $start = max($start, $calc($d));
                }
            }
            unset($visiting[$code]);
            $es[$code] = $start;

            return $ef[$code] = $start + max(0, (int) $tasks[$code]['duration']);
        };
        foreach (array_keys($tasks) as $code) {
            $calc($code);
        }

        return ['total' => $ef ? max($ef) : 0, 'es' => $es, 'ef' => $ef];
    }

    public static function masterTaskGraph(Project $project): array
    {
        $tasks = [];
        foreach (self::stageMasters($project) as $st) {
            foreach ($st->subtasks as $t) {
                $tasks[$t->task_code] = ['duration' => (int) $t->default_duration_days, 'deps' => $t->dependencies->pluck('task_code')->all()];
            }
        }

        return $tasks;
    }

    /** Save the estimate (facility + stage/approval + other costs) and its derived figures. */
    public static function saveEstimate(Project $project, array $input, Tenant $tenant): ProjectEstimate
    {
        return DB::transaction(function () use ($project, $input, $tenant) {
            $settings = $tenant->setting();
            $estimate = ProjectEstimate::firstOrNew(['project_id' => $project->id]);
            $estimate->fill([
                'tenant_id' => $project->tenant_id,
                'tier' => $input['tier'],
                'sellable_pct' => $input['sellable_pct'] ?? $settings->sellable_pct,
                'mrp_multiplier' => $input['mrp_multiplier'] ?? $settings->mrp_multiplier,
            ]);
            $estimate->save();
            $estimate->lines()->delete();

            $facilities = FacilityMaster::whereIn('id', array_keys($input['facility'] ?? []))->get()->keyBy('id');
            foreach ($input['facility'] ?? [] as $id => $row) {
                if (empty($row['include']) || ! isset($facilities[$id])) {
                    continue;
                }
                $f = $facilities[$id];
                $qty = (float) ($row['qty'] ?? 0);
                $cost = (float) ($row['cost'] ?? $f->costFor($project->approval_type));
                EstimateLine::create(['tenant_id' => $project->tenant_id, 'project_estimate_id' => $estimate->id, 'line_type' => 'facility', 'facility_master_id' => $f->id,
                    'description' => $f->name, 'unit' => $f->unit, 'quantity' => $qty, 'unit_cost' => $cost, 'amount' => round($qty * $cost, 2)]);
            }
            foreach ($input['stage'] ?? [] as $no => $row) {
                if (array_key_exists('include', $row) && empty($row['include'])) {
                    continue;
                }
                $cost = (float) ($row['cost'] ?? 0);
                EstimateLine::create(['tenant_id' => $project->tenant_id, 'project_estimate_id' => $estimate->id, 'line_type' => 'stage', 'stage_no' => (int) $no,
                    'description' => (string) ($row['name'] ?? 'Stage '.$no), 'unit' => 'lump sum', 'quantity' => 1, 'unit_cost' => $cost, 'amount' => $cost]);
            }
            foreach ($input['other'] ?? [] as $row) {
                if (trim((string) ($row['description'] ?? '')) === '') {
                    continue;
                }
                $qty = (float) ($row['qty'] ?? 1);
                $cost = (float) ($row['cost'] ?? 0);
                EstimateLine::create(['tenant_id' => $project->tenant_id, 'project_estimate_id' => $estimate->id, 'line_type' => 'other',
                    'description' => mb_substr($row['description'], 0, 255), 'unit' => $row['unit'] ?? 'lump sum', 'quantity' => $qty, 'unit_cost' => $cost, 'amount' => round($qty * $cost, 2)]);
            }
            self::recalcEstimate($estimate->fresh('lines'), $project);
            if (in_array($project->status, ['draft', 'init'], true)) {
                self::setStatus($project, 'go_no_go', 'Estimate saved — ready for the Go / No-Go decision.');
            }

            return $estimate->fresh('lines');
        });
    }

    public static function recalcEstimate(ProjectEstimate $e, Project $project): void
    {
        $facility = $e->lines->where('line_type', 'facility')->sum('amount');
        $stage = $e->lines->where('line_type', 'stage')->sum('amount');
        $other = $e->lines->where('line_type', 'other')->sum('amount');
        $total = $facility + $stage + $other;
        $totalSqft = $project->totalSqft();
        $actualSellable = (float) $project->plots()->sum('size_sqft');
        $sellable = $actualSellable > 0 ? $actualSellable : round($totalSqft * (float) $e->sellable_pct / 100, 2);
        $perSqft = $sellable > 0 ? round($total / $sellable, 2) : 0;
        $e->fill([
            'facility_cost' => $facility,
            'stage_cost' => $stage,
            'other_cost' => $other,
            'total_cost' => $total,
            'total_sqft' => $totalSqft,
            'sellable_sqft' => $sellable,
            'cost_per_sqft' => $perSqft,
            'mrp_per_sqft' => round($perSqft * (float) $e->mrp_multiplier, 2),
            'est_duration_days' => self::criticalPath(self::masterTaskGraph($project))['total'],
        ])->save();
    }

    public static function decide(Project $project, string $decision, ?string $notes, int $userId): void
    {
        if ($project->status !== 'go_no_go') {
            throw ValidationException::withMessages(['decision' => 'Save the estimate before recording the Go / No-Go decision.']);
        }
        $project->update(['decision' => $decision, 'decision_on' => today(), 'decision_by' => $userId, 'decision_notes' => $notes]);
        if ($decision === 'no_go') {
            self::setStatus($project, 'closed', 'No-Go decision: '.($notes ?: 'project closed'));
        } else {
            self::notify($project, 'Go decision recorded. '.($notes ?? ''));
        }
    }

    /** Start: create stages/sub-tasks/documents from the masters and schedule them in working days. */
    public static function start(Project $project, Carbon $startDate, ?Carbon $endOverride = null): void
    {
        if ($project->status !== 'go_no_go' || $project->decision !== 'go') {
            throw ValidationException::withMessages(['start_date' => 'Record a Go decision before starting the project.']);
        }
        DB::transaction(function () use ($project, $startDate, $endOverride) {
            $wd = WorkingDays::for($project->tenant_id);
            $masters = self::stageMasters($project);
            $graph = self::masterTaskGraph($project);
            $cp = self::criticalPath($graph);
            $estimate = $project->estimate;
            $stageBudgets = $estimate ? $estimate->lines->where('line_type', 'stage')->pluck('amount', 'stage_no')->map(fn ($v) => (float) $v)->all() : [];

            foreach ($masters as $sm) {
                $stage = ProjectStage::create([
                    'tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'stage_no' => $sm->stage_no, 'name' => $sm->name,
                    'budget' => $stageBudgets[$sm->stage_no] ?? (float) $sm->default_cost + (float) $sm->subtasks->sum('default_cost'),
                ]);
                $totalDur = max(1, $sm->subtasks->sum('default_duration_days'));
                $minStart = null;
                $maxEnd = null;
                foreach ($sm->subtasks as $t) {
                    $ps = $wd->add($startDate, $cp['es'][$t->task_code] ?? 0);
                    $pe = $wd->add($startDate, $cp['ef'][$t->task_code] ?? 0);
                    $budget = (float) $t->default_cost > 0 ? (float) $t->default_cost : round($stage->budget * max(1, $t->default_duration_days) / $totalDur, 2);
                    $sub = ProjectSubtask::create([
                        'tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'project_stage_id' => $stage->id,
                        'task_code' => $t->task_code, 'short_name' => $t->short_name, 'name' => $t->name, 'responsible' => $t->responsible,
                        'duration_days' => $t->default_duration_days, 'depends_on' => $t->dependencies->pluck('task_code')->values()->all(),
                        'planned_start' => $ps, 'planned_end' => $pe, 'budget' => $budget,
                    ]);
                    foreach ($t->documents as $doc) {
                        ProjectDocument::create([
                            'tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'project_subtask_id' => $sub->id,
                            'doc_code' => $doc->doc_code, 'name' => $doc->name, 'is_mandatory' => $doc->mandatory === 'Yes',
                        ]);
                    }
                    $minStart = $minStart ? min($minStart, $ps) : $ps;
                    $maxEnd = $maxEnd ? max($maxEnd, $pe) : $pe;
                }
                $stage->update(['planned_start' => $minStart, 'planned_end' => $maxEnd]);
            }
            $estEnd = $endOverride ?? $wd->add($startDate, $cp['total']);
            $project->update(['start_date' => $startDate, 'est_end_date' => $estEnd]);
            self::setStatus($project, 'in_progress', 'Project started on '.Format::date($startDate).'; estimated end '.Format::date($estEnd).'.');
        });
    }

    /**
     * Update a sub-task. Done only when its dependencies are Done and its mandatory documents are uploaded.
     */
    public static function updateSubtask(ProjectSubtask $sub, array $data, int $userId): void
    {
        $project = $sub->project;
        if ($project->status !== 'in_progress') {
            throw ValidationException::withMessages(['status' => 'Tracking is only open while the project is In Progress.']);
        }
        if (($data['status'] ?? $sub->status) === 'done') {
            $pending = ProjectSubtask::where('project_id', $project->id)->whereIn('task_code', $sub->depends_on ?? [])->where('status', '!=', 'done')->pluck('task_code');
            if ($pending->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => 'Finish the tasks this depends on first: '.$pending->implode(', ').'.']);
            }
            $missing = $sub->documents()->where('is_mandatory', true)->whereNull('storage_file_id')->pluck('doc_code');
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => 'Upload the mandatory documents first: '.$missing->implode(', ').'.']);
            }
            $data['actual_end'] = $data['actual_end'] ?? today();
            $data['actual_start'] = $data['actual_start'] ?? $sub->actual_start ?? today();
        }
        if (($data['status'] ?? null) === 'in_progress' && ! $sub->actual_start && empty($data['actual_start'])) {
            $data['actual_start'] = today();
        }
        $sub->update($data + ['updated_by' => $userId]);
        self::recalc($project->fresh());
    }

    /** Roll up stage & project progress (duration-weighted) and actual costs; fire budget alerts. */
    public static function recalc(Project $project): void
    {
        $project->load(['stages.subtasks', 'estimate.lines']);
        $allDur = 0;
        $doneDur = 0;
        foreach ($project->stages as $stage) {
            $dur = $stage->subtasks->sum(fn ($s) => max(1, $s->duration_days));
            $done = $stage->subtasks->where('status', 'done')->sum(fn ($s) => max(1, $s->duration_days));
            $allDur += $dur;
            $doneDur += $done;
            $expenses = (float) Expense::where('project_stage_id', $stage->id)->sum('amount');
            $actual = (float) $stage->subtasks->sum('actual_cost') + $expenses;
            $status = $done === $dur && $dur > 0 ? 'done' : ($stage->subtasks->whereIn('status', ['in_progress', 'done', 'blocked'])->isNotEmpty() ? 'in_progress' : 'not_started');
            $stage->update([
                'progress_pct' => $dur ? round($done / $dur * 100, 2) : 0,
                'actual_cost' => $actual,
                'status' => $status,
                'actual_start' => $stage->subtasks->whereNotNull('actual_start')->min('actual_start'),
                'actual_end' => $status === 'done' ? $stage->subtasks->max('actual_end') : null,
            ]);
        }
        if ($project->estimate) {
            foreach ($project->estimate->lines->where('line_type', 'facility') as $line) {
                $line->update(['actual_cost' => (float) Expense::where('estimate_line_id', $line->id)->sum('amount')]);
            }
        }
        $project->update(['progress_pct' => $allDur ? round($doneDur / $allDur * 100, 2) : 0]);
        self::budgetAlerts($project->fresh(['stages', 'estimate.lines']));
    }

    public static function projectSpend(Project $project): float
    {
        $subtaskCost = (float) ProjectSubtask::where('project_id', $project->id)->sum('actual_cost');
        $expenses = (float) Expense::where('project_id', $project->id)->whereNull('refund_id')->sum('amount');

        return $subtaskCost + $expenses;
    }

    /** Email Management + Accounts when spend crosses the alert % of budget (stage, facility, project) — once per level. */
    public static function budgetAlerts(Project $project): void
    {
        $tenant = Tenant::find($project->tenant_id);
        $pct = (float) $tenant->setting()->budget_alert_pct;
        $sent = $project->budget_alerts_sent ?? [];
        $checks = [];
        foreach ($project->stages as $s) {
            $checks['stage:'.$s->id] = ['Stage '.$s->stage_no.' – '.$s->name, (float) $s->budget, (float) $s->actual_cost];
        }
        foreach ($project->estimate?->lines->where('line_type', 'facility') ?? [] as $l) {
            $checks['facility:'.$l->id] = ['Facility – '.$l->description, (float) $l->amount, (float) $l->actual_cost];
        }
        $checks['project'] = ['Whole project', (float) ($project->estimate?->total_cost ?? 0), self::projectSpend($project)];
        $changed = false;
        foreach ($checks as $key => [$scope, $budget, $spent]) {
            if ($budget <= 0) {
                continue;
            }
            $used = round($spent / $budget * 100, 1);
            foreach ([100, $pct] as $level) {
                $k = $key.'@'.$level;
                if ($used >= $level && ! isset($sent[$k])) {
                    Notify::groups($project->tenant_id, ['Management', 'Accounts'], 'budget_alert', [
                        'scope' => $scope, 'project' => $project->name, 'pct' => $used, 'budget' => Format::inr($budget), 'spent' => Format::inr($spent),
                    ]);
                    $sent[$k] = now()->toDateTimeString();
                    $changed = true;
                    break;
                }
            }
        }
        if ($changed) {
            $project->forceFill(['budget_alerts_sent' => $sent])->saveQuietly();
        }
    }

    /**
     * Ready to Launch: every sub-task is Done except those of the final "Sub-division & Sale" stage
     * (marketing and sale deeds happen after launch).
     */
    public static function readinessGaps(Project $project): array
    {
        $lastStage = $project->stages()->max('stage_no');

        return ProjectSubtask::where('project_id', $project->id)->where('status', '!=', 'done')
            ->whereHas('stage', fn ($q) => $q->where('stage_no', '<', $lastStage))->pluck('task_code')->all();
    }

    public static function markReady(Project $project): void
    {
        if ($project->status !== 'in_progress') {
            throw ValidationException::withMessages(['ready' => 'Only an In Progress project can be marked Ready to Launch.']);
        }
        $gaps = self::readinessGaps($project);
        if ($gaps) {
            throw ValidationException::withMessages(['ready' => 'These approval tasks are not done yet: '.implode(', ', array_slice($gaps, 0, 12)).(count($gaps) > 12 ? '…' : '')]);
        }
        $project->update(['actual_end_date' => today()]);
        self::setStatus($project, 'ready_to_launch', 'All approval stages are complete.');
    }

    public static function setStatus(Project $project, string $status, string $details = ''): void
    {
        $project->update(['status' => $status]);
        self::notify($project, $details);
    }

    private static function notify(Project $project, string $details): void
    {
        Notify::groups($project->tenant_id, ['Management'], 'project_status', [
            'project' => $project->name, 'code' => $project->project_code, 'status' => $project->statusLabel(), 'details' => $details,
        ]);
    }
}
