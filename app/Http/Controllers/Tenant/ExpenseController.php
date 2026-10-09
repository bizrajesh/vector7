<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\EstimateLine;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Services\FileStore;
use App\Services\IdGenerator;
use App\Services\ProjectService;
use App\Support\Excel;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Project expenses with category, stage / facility link, vendor and bill upload. Budget alerts recalculated. */
class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $q = Expense::with(['project', 'stage', 'estimateLine', 'category', 'bill'])->latest('spent_on')->latest('id')
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('category'), fn ($q, $c) => $q->where('expense_category_id', $c))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('spent_on', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('spent_on', '<=', $d))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('vendor', 'like', "%$s%")->orWhere('description', 'like', "%$s%")->orWhere('transaction_no', 'like', "%$s%")));
        if ($request->query('export') === 'xlsx') {
            return Excel::download('expenses.xlsx', ['Txn', 'Date', 'Project', 'Stage', 'Facility', 'Category', 'Vendor', 'Description', 'Mode', 'Ref', 'Amount'],
                $q->get()->map(fn ($e) => [$e->transaction_no, $e->spent_on, $e->project?->name, $e->stage?->name, $e->estimateLine?->description, $e->category?->name, $e->vendor, $e->description, $e->mode, $e->reference_no, (float) $e->amount]), 'Expenses');
        }
        $projects = Project::whereIn('status', ['in_progress', 'ready_to_launch', 'launched', 'go_no_go', 'init'])->orderBy('name')->get();

        return view('ws.accounts.expenses', [
            'expenses' => $q->paginate(30)->withQueryString(),
            'total' => (clone $q)->sum('amount'),
            'projects' => $projects,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'stages' => ProjectStage::whereIn('project_id', $projects->pluck('id'))->orderBy('stage_no')->get()->groupBy('project_id'),
            'lines' => EstimateLine::where('line_type', 'facility')->whereHas('estimate', fn ($e) => $e->whereIn('project_id', $projects->pluck('id')))->with('estimate')->get()->groupBy(fn ($l) => $l->estimate->project_id),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $tenantId = app(Tenancy::class)->id();
        $e = Expense::create($data + ['tenant_id' => $tenantId, 'transaction_no' => IdGenerator::next($tenantId, 'expense'), 'created_by' => $request->user()->id]);
        if ($request->hasFile('bill')) {
            $e->update(['bill_file_id' => FileStore::store($request->file('bill'), 'bill', $tenantId, $e)->id]);
        }
        $this->recalc($e);

        return back()->with('ok', "Expense {$e->transaction_no} recorded.");
    }

    public function update(Request $request, Expense $expense)
    {
        abort_if($expense->refund_id, 403, 'Refund expenses are created by the refund approval and cannot be edited.');
        $expense->update($this->validated($request) + ['updated_by' => $request->user()->id]);
        if ($request->hasFile('bill')) {
            $old = $expense->bill;
            $expense->update(['bill_file_id' => FileStore::store($request->file('bill'), 'bill', $expense->tenant_id, $expense)->id]);
            FileStore::delete($old);
        }
        $this->recalc($expense);

        return back()->with('ok', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->refund_id, 403, 'Refund expenses cannot be deleted.');
        $project = $expense->project;
        FileStore::delete($expense->bill);
        $expense->delete();
        if ($project && $project->stages()->exists()) {
            ProjectService::recalc($project);
        }

        return back()->with('ok', 'Expense deleted.');
    }

    public function category(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('expense_categories', 'name')->where('tenant_id', app(Tenancy::class)->id())]]);
        ExpenseCategory::create($data + ['tenant_id' => app(Tenancy::class)->id()]);

        return back()->with('ok', 'Category added.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'project_id' => 'nullable|integer',
            'project_stage_id' => 'nullable|integer',
            'estimate_line_id' => 'nullable|integer',
            'expense_category_id' => 'required|integer',
            'vendor' => 'nullable|string|max:150',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'spent_on' => 'required|date|before_or_equal:today',
            'mode' => 'nullable|in:cash,cheque,bank_transfer,upi',
            'reference_no' => 'nullable|string|max:60',
            'bill' => FileStore::rules('document', 10240, false),
        ]);
        unset($data['bill']);
        // links must belong to this tenant (scope) and to the chosen project
        ExpenseCategory::findOrFail($data['expense_category_id']);
        if (! empty($data['project_id'])) {
            Project::findOrFail($data['project_id']);
        }
        if (! empty($data['project_stage_id'])) {
            $stage = ProjectStage::findOrFail($data['project_stage_id']);
            $data['project_id'] = $stage->project_id;
        }
        if (! empty($data['estimate_line_id'])) {
            $line = EstimateLine::with('estimate')->findOrFail($data['estimate_line_id']);
            $data['project_id'] = $line->estimate->project_id;
        }

        return $data;
    }

    private function recalc(Expense $e): void
    {
        if ($e->project_id && ($p = Project::find($e->project_id)) && $p->stages()->exists()) {
            ProjectService::recalc($p);
        } elseif ($e->project_id && ($p = Project::find($e->project_id))) {
            ProjectService::budgetAlerts($p->load(['stages', 'estimate.lines']));
        }
    }
}
