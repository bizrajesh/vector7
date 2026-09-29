<?php

namespace App\Services;

use App\Enums\LayoutStatus;
use App\Models\Layout;
use App\Models\ProjectStage;
use App\Models\ProjectTask;
use App\Models\StageGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stage group snapshot, dependency scheduling and execution (sections 4.2 and 5).
 */
class StageService
{
    public function __construct(private readonly Notifier $notifier, private readonly Settings $settings) {}

    /** Copy the group's stages, tasks and links into the project (snapshot). */
    public function instantiate(Layout $layout, StageGroup $group): void
    {
        abort_unless($layout->isEditable(), 422, 'Stages can only be changed while the project is a draft.');

        DB::transaction(function () use ($layout, $group) {
            $layout->stages()->each(fn (ProjectStage $s) => $s->delete());
            $layout->stage_group_id = $group->id;
            $layout->save();

            $map = [];
            foreach ($group->stages()->with(['tasks', 'dependsOn'])->get() as $template) {
                $stage = new ProjectStage([
                    'stage_no' => $template->stage_no, 'seq_no' => $template->seq_no, 'name' => $template->name,
                    'description' => $template->description, 'stage_type' => $template->stage_type,
                    'is_mandatory' => $template->is_mandatory, 'budget_cost' => $template->cost,
                    'duration_days' => $template->duration_days,
                ]);
                $stage->layout_id = $layout->id;
                $stage->stage_template_id = $template->id;
                $stage->save();
                $map[$template->id] = $stage;

                foreach ($template->tasks as $taskTemplate) {
                    $task = new ProjectTask($taskTemplate->only(['task_no', 'name', 'description', 'effort_days', 'weight_pct']));
                    $task->project_stage_id = $stage->id;
                    $task->save();
                }
            }

            foreach ($group->stages()->with('dependsOn')->get() as $template) {
                foreach ($template->dependsOn as $dependency) {
                    if (isset($map[$template->id], $map[$dependency->id])) {
                        $map[$template->id]->dependsOn()->attach($map[$dependency->id]->id, ['tenant_id' => $layout->tenant_id]);
                    }
                }
            }
        });

        $this->schedule($layout);
    }

    /** Replace a stage's dependencies after checking the graph stays acyclic. */
    public function setDependencies(ProjectStage $stage, array $dependsOnIds): void
    {
        $ids = ProjectStage::query()->where('layout_id', $stage->layout_id)->whereIn('id', $dependsOnIds)
            ->where('id', '!=', $stage->id)->pluck('id')->all();

        $graph = $this->graph($stage->layout);
        $graph[$stage->id] = $ids;
        if ($this->hasCycle($graph)) {
            throw ValidationException::withMessages(['depends_on' => 'These links would create a circular dependency.']);
        }

        $stage->dependsOn()->sync(array_fill_keys($ids, ['tenant_id' => $stage->tenant_id]));
        $stage->stage_type = $ids ? 'dependent' : 'independent';
        $stage->save();
        $this->schedule($stage->layout);
    }

    /** Planned dates: independent stages start on day 1; dependent stages after all predecessors. */
    public function schedule(Layout $layout, ?CarbonImmutable $start = null): void
    {
        $start ??= CarbonImmutable::parse($layout->submitted_at ?? now())->startOfDay();
        $stages = $layout->stages()->with('dependsOn:project_stages.id')->get()->keyBy('id');
        $ends = [];

        $resolve = function (ProjectStage $stage) use (&$resolve, &$ends, $stages, $start) {
            if (isset($ends[$stage->id])) {
                return $ends[$stage->id];
            }
            $begin = $start;
            foreach ($stage->dependsOn as $dep) {
                if ($stages->has($dep->id)) {
                    $begin = max($begin, $resolve($stages[$dep->id])->addDay());
                }
            }
            $end = $begin->addDays(max(1, (int) $stage->duration_days) - 1);
            $stage->planned_start = $begin;
            $stage->planned_end = $end;
            $stage->saveQuietly();

            return $ends[$stage->id] = $end;
        };

        $stages->each(fn ($s) => $resolve($s));
    }

    public function totalDays(Layout $layout): int
    {
        $min = $layout->stages()->min('planned_start');
        $max = $layout->stages()->max('planned_end');

        return ($min && $max) ? CarbonImmutable::parse($min)->diffInDays(CarbonImmutable::parse($max)) + 1 : 0;
    }

    public function start(ProjectStage $stage): void
    {
        $layout = $stage->layout;
        abort_unless(in_array($layout->status, [LayoutStatus::Submitted, LayoutStatus::InProgress], true), 422, 'Submit the project before starting stages.');
        abort_unless($stage->status === 'pending', 422, 'Stage already started.');

        $blocking = $stage->dependsOn()->whereNotIn('status', ['completed', 'skipped'])->pluck('name');
        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages(['stage' => 'Complete first: '.$blocking->implode(', ')]);
        }

        $stage->status = 'in_progress';
        $stage->actual_start = now();
        $stage->save();

        if ($layout->status === LayoutStatus::Submitted) {
            $layout->status = LayoutStatus::InProgress;
            $layout->save();
        }

        $this->notifier->event('stage.started', "Stage {$stage->name} started on {$layout->name}.", $layout);
    }

    public function toggleTask(ProjectTask $task, bool $done, ?string $notes = null, ?string $evidencePath = null): void
    {
        $stage = $task->stage;
        abort_unless($stage->status === 'in_progress', 422, 'Start the stage before updating tasks.');

        $task->is_done = $done;
        $task->done_at = $done ? now() : null;
        $task->done_by = $done ? auth()->id() : null;
        $task->notes = $notes ?? $task->notes;
        if ($evidencePath) {
            $task->evidence_path = $evidencePath;
        }
        $task->save();

        $stage->progress_pct = min(100, (float) $stage->tasks()->where('is_done', true)->sum('weight_pct'));
        $stage->save();
    }

    public function complete(ProjectStage $stage): void
    {
        abort_unless($stage->status === 'in_progress', 422, 'Only a stage in progress can be completed.');
        if ($stage->tasks()->where('is_done', false)->exists()) {
            throw ValidationException::withMessages(['stage' => 'Finish all tasks before completing the stage.']);
        }

        $stage->status = 'completed';
        $stage->actual_end = now();
        $stage->progress_pct = 100;
        $stage->save();

        $this->notifier->event('stage.completed', "Stage {$stage->name} completed on {$stage->layout->name}.", $stage->layout);
        $this->refreshReadiness($stage->layout);
    }

    public function skip(ProjectStage $stage, string $reason): void
    {
        abort_if($stage->is_mandatory, 422, 'Mandatory stages cannot be skipped.');
        abort_unless($stage->status === 'pending', 422, 'Only a pending stage can be skipped.');

        $stage->status = 'skipped';
        $stage->skip_reason = $reason;
        $stage->save();
        $this->refreshReadiness($stage->layout);
    }

    /** All mandatory stages completed → Ready to Launch. */
    public function refreshReadiness(Layout $layout): void
    {
        $open = $layout->stages()->where('is_mandatory', true)->where('status', '!=', 'completed')->exists();

        if (! $open && $layout->status === LayoutStatus::InProgress) {
            $layout->status = LayoutStatus::ReadyToLaunch;
            $layout->save();
        }
    }

    /** Expense vs budget threshold alert (section 4.1). */
    public function checkBudget(ProjectStage $stage): void
    {
        $budget = (float) $stage->budget_cost;
        if ($budget <= 0) {
            return;
        }
        $pct = $stage->actualCost() / $budget * 100;
        $threshold = (float) $this->settings->get('budget_alert_pct', 80);

        if ($pct >= $threshold) {
            $this->notifier->event('budget.threshold', sprintf('%s on %s is at %.0f%% of budget.', $stage->name, $stage->layout->name, $pct), $stage->layout);
        }
    }

    private function graph(Layout $layout): array
    {
        return $layout->stages()->with('dependsOn:project_stages.id')->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->dependsOn->pluck('id')->all()])->all();
    }

    private function hasCycle(array $graph): bool
    {
        $state = [];
        $visit = function ($node) use (&$visit, &$state, $graph): bool {
            if (($state[$node] ?? 0) === 1) {
                return true;
            }
            if (($state[$node] ?? 0) === 2) {
                return false;
            }
            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $next) {
                if ($visit($next)) {
                    return true;
                }
            }
            $state[$node] = 2;

            return false;
        };

        foreach (array_keys($graph) as $node) {
            if ($visit($node)) {
                return true;
            }
        }

        return false;
    }
}
