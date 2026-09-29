<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ProjectStage;
use App\Models\ProjectTask;
use App\Services\FileVault;
use App\Services\LedgerService;
use App\Services\StageService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Stage execution (Operations view). */
class ProjectStageController extends Controller
{
    public function __construct(private readonly StageService $stages) {}

    public function update(Request $request, ProjectStage $stage): RedirectResponse
    {
        abort_unless($stage->layout->isEditable() || $stage->status === 'pending', 422, 'Only pending stages can be re-estimated.');

        $data = $request->validate([
            'budget_cost' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', app(TenantContext::class)->id())],
        ]);
        $stage->update($data);
        $this->stages->schedule($stage->layout);

        return $this->back($stage, 'Stage estimate updated.');
    }

    public function dependencies(Request $request, ProjectStage $stage): RedirectResponse
    {
        abort_unless($stage->layout->isEditable(), 422, 'Dependencies are locked after submit.');
        $data = $request->validate(['depends_on' => ['array'], 'depends_on.*' => ['integer']]);
        $this->stages->setDependencies($stage, $data['depends_on'] ?? []);

        return $this->back($stage, 'Dependencies updated.');
    }

    public function start(ProjectStage $stage): RedirectResponse
    {
        $this->stages->start($stage);

        return $this->back($stage, 'Stage started.');
    }

    public function complete(ProjectStage $stage): RedirectResponse
    {
        $this->stages->complete($stage);

        return $this->back($stage, 'Stage completed.');
    }

    public function skip(Request $request, ProjectStage $stage): RedirectResponse
    {
        $this->stages->skip($stage, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']);

        return $this->back($stage, 'Stage skipped.');
    }

    public function toggleTask(Request $request, ProjectTask $task, FileVault $vault): RedirectResponse
    {
        $data = $request->validate([
            'done' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'evidence' => ['nullable', 'file', 'max:'.config('vector7.uploads.max_kb'), 'mimes:'.implode(',', config('vector7.uploads.mimes'))],
        ]);
        $path = $request->hasFile('evidence') ? $vault->store($request->file('evidence'), 'task-evidence') : null;
        $this->stages->toggleTask($task, (bool) $data['done'], $data['notes'] ?? null, $path);

        return $this->back($task->stage, 'Task updated.');
    }

    public function storeExpense(Request $request, ProjectStage $stage, LedgerService $ledger, FileVault $vault): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'party' => ['nullable', 'string', 'max:150'],
            'mode' => ['required', Rule::in(['cash', 'upi', 'neft', 'cheque', 'card'])],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:255'],
            'ledger_category_id' => ['nullable', Rule::exists('ledger_categories', 'id')->where('tenant_id', app(TenantContext::class)->id())->where('direction', 'out')],
            'attachment' => ['nullable', 'file', 'max:'.config('vector7.uploads.max_kb'), 'mimes:'.implode(',', config('vector7.uploads.mimes'))],
        ]);

        $ledger->post([
            'layout_id' => $stage->layout_id, 'project_stage_id' => $stage->id, 'direction' => 'out', 'type' => 'expense',
            'attachment_path' => $request->hasFile('attachment') ? $vault->store($request->file('attachment'), 'expenses') : null,
        ] + $data, $stage);

        $this->stages->checkBudget($stage);

        return $this->back($stage, 'Expense recorded.');
    }

    private function back(ProjectStage $stage, string $message): RedirectResponse
    {
        return redirect()->route('app.layouts.show', ['layout' => $stage->layout_id, 'tab' => 'stages'])->with('status', $message);
    }
}
