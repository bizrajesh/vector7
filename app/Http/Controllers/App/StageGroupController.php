<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\StageGroup;
use App\Models\StageTemplate;
use App\Models\TaskTemplate;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Section 4.2: stage groups, stages (sequence, cost, duration, type, links) and tasks. */
class StageGroupController extends Controller
{
    public function index(): View
    {
        return view('app.settings.stage-groups.index', ['groups' => StageGroup::query()->withCount('stages')->withSum('stages', 'cost')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = StageGroup::create($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]) + ['is_active' => true]);

        return $this->done('Stage group created.', 'app.settings.stage-groups.show', $group);
    }

    public function show(StageGroup $stageGroup): View
    {
        return view('app.settings.stage-groups.show', [
            'group' => $stageGroup->load(['stages.tasks', 'stages.dependsOn']),
        ]);
    }

    public function update(Request $request, StageGroup $stageGroup): RedirectResponse
    {
        $stageGroup->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]));

        return $this->done('Stage group updated.');
    }

    public function destroy(StageGroup $stageGroup): RedirectResponse
    {
        $stageGroup->delete();

        return $this->done('Stage group archived.', 'app.settings.stage-groups.index');
    }

    public function storeStage(Request $request, StageGroup $stageGroup): RedirectResponse
    {
        $data = $this->validateStage($request, $stageGroup);

        DB::transaction(function () use ($data, $stageGroup) {
            $stage = new StageTemplate(Arr::except($data, 'depends_on'));
            $stage->stage_group_id = $stageGroup->id;
            $stage->stage_no = $this->nextStageNo();
            $stage->stage_type = empty($data['depends_on']) ? 'independent' : 'dependent';
            $stage->save();
            $this->syncLinks($stage, $data['depends_on'] ?? []);
        });

        return $this->done('Stage added.');
    }

    public function updateStage(Request $request, StageGroup $stageGroup, StageTemplate $stage): RedirectResponse
    {
        $data = $this->validateStage($request, $stageGroup, $stage);

        if ($stage->tasks()->sum('effort_days') > $data['duration_days']) {
            throw ValidationException::withMessages(['duration_days' => 'Duration cannot be shorter than the total task effort.']);
        }

        DB::transaction(function () use ($stage, $data) {
            $stage->fill(Arr::except($data, 'depends_on'));
            $stage->stage_type = empty($data['depends_on']) ? 'independent' : 'dependent';
            $stage->save();
            $this->syncLinks($stage, $data['depends_on'] ?? []);
        });

        return $this->done('Stage updated.');
    }

    public function destroyStage(StageGroup $stageGroup, StageTemplate $stage): RedirectResponse
    {
        $stage->delete();

        return $this->done('Stage removed.');
    }

    public function storeTask(Request $request, StageGroup $stageGroup, StageTemplate $stage): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'effort_days' => ['required', 'numeric', 'min:0.25', 'max:365'],
            'weight_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        // Task effort must fit inside the stage duration; task weights must not exceed 100%.
        if ($stage->tasks()->sum('effort_days') + $data['effort_days'] > $stage->duration_days) {
            throw ValidationException::withMessages(['effort_days' => "Total task effort cannot exceed the stage duration ({$stage->duration_days} days)."]);
        }
        if ($stage->tasks()->sum('weight_pct') + $data['weight_pct'] > 100) {
            throw ValidationException::withMessages(['weight_pct' => 'Task weights for a stage cannot exceed 100%.']);
        }

        $task = new TaskTemplate($data);
        $task->stage_template_id = $stage->id;
        $task->task_no = sprintf('T%03d', TaskTemplate::query()->count() + 1);
        $task->save();

        return $this->done('Task added.');
    }

    public function destroyTask(StageGroup $stageGroup, TaskTemplate $task): RedirectResponse
    {
        abort_unless($task->stage?->stage_group_id === $stageGroup->id, 404);
        $task->delete();

        return $this->done('Task removed.');
    }

    private function validateStage(Request $request, StageGroup $group, ?StageTemplate $stage = null): array
    {
        return $request->validate([
            'seq_no' => ['required', 'integer', 'min:1', 'max:999'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'cost' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_mandatory' => ['required', 'boolean'],
            'depends_on' => ['nullable', 'array'],
            'depends_on.*' => ['integer', Rule::exists('stage_templates', 'id')->where('stage_group_id', $group->id)->where('tenant_id', app(TenantContext::class)->id()), Rule::notIn([$stage?->id])],
        ]);
    }

    private function syncLinks(StageTemplate $stage, array $ids): void
    {
        $stage->dependsOn()->sync(array_fill_keys(array_map('intval', $ids), ['tenant_id' => $stage->tenant_id]));
    }

    private function nextStageNo(): string
    {
        $max = StageTemplate::query()->selectRaw("MAX(CAST(SUBSTRING(stage_no, 3) AS UNSIGNED)) AS n")->value('n');

        return sprintf('SG%03d', (int) $max + 1);
    }
}
