<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Instalment;
use App\Models\Payment;
use App\Models\Project;
use App\Services\ProjectService;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Accounts: receipts (income), expenses, budget vs actual, day book, receivables. */
class AccountsController extends Controller
{
    public function index()
    {
        $month = now()->startOfMonth();

        return view('ws.accounts.index', [
            'today' => (float) Payment::whereDate('paid_on', today())->sum('amount'),
            'month' => (float) Payment::whereDate('paid_on', '>=', $month)->sum('amount'),
            'expMonth' => (float) Expense::whereDate('spent_on', '>=', $month)->sum('amount'),
            'receivable' => (float) Instalment::where('status', '!=', 'paid')->whereHas('sale', fn ($q) => $q->where('status', 'sale_init'))->get()->sum(fn ($i) => $i->balance()),
            'overdue' => (float) Instalment::where('status', '!=', 'paid')->whereDate('due_date', '<', today())->whereHas('sale', fn ($q) => $q->where('status', 'sale_init'))->get()->sum(fn ($i) => $i->balance()),
            'recent' => Payment::with(['plot', 'customer', 'project'])->latest('paid_on')->latest('id')->take(8)->get(),
            'recentExp' => Expense::with(['project', 'category'])->latest('spent_on')->latest('id')->take(8)->get(),
        ]);
    }

    public function receipts(Request $request)
    {
        $q = Payment::with(['plot', 'customer', 'project', 'receipt', 'sale'])->latest('paid_on')->latest('id')
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->query('mode'), fn ($q, $m) => $q->where('mode', $m))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('paid_on', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('paid_on', '<=', $d));
        if ($request->query('export') === 'xlsx') {
            return Excel::download('receipts.xlsx', ['Receipt', 'Date', 'Type', 'Project', 'Plot', 'Customer', 'Mode', 'Reference', 'Amount'],
                $q->get()->map(fn ($p) => [$p->transaction_no, $p->paid_on, $p->kind === 'booking' ? 'Booking receipt' : 'Sales receipt', $p->project->name, $p->plot->plot_no, $p->customer->name, Payment::MODES[$p->mode], $p->reference_no, (float) $p->amount]), 'Receipts (income)');
        }

        return view('ws.accounts.receipts', ['payments' => $q->paginate(30)->withQueryString(), 'total' => (clone $q)->sum('amount'), 'projects' => Project::orderBy('name')->pluck('name', 'id')]);
    }

    public function budget(Request $request)
    {
        $projects = Project::whereHas('estimate')->with(['estimate.lines', 'stages'])->orderBy('name')->get();
        $selected = $request->query('project') ? $projects->firstWhere('id', (int) $request->query('project')) : $projects->first();
        $rows = $projects->map(fn ($p) => ['project' => $p, 'budget' => (float) $p->estimate->total_cost, 'spent' => ProjectService::projectSpend($p)]);

        return view('ws.accounts.budget', ['rows' => $rows, 'selected' => $selected, 'projects' => $projects->pluck('name', 'id'), 'alertPct' => (float) app(\App\Support\Tenancy::class)->get()->setting()->budget_alert_pct]);
    }

    public function dayBook(Request $request)
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : today()->startOfMonth();
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : today();
        $income = Payment::with(['plot', 'customer', 'project'])->whereBetween('paid_on', [$from, $to])->get()->map(fn ($p) => [
            'date' => $p->paid_on, 'txn' => $p->transaction_no, 'particulars' => ($p->kind === 'booking' ? 'Booking receipt' : 'Sales receipt')." — {$p->customer->name}, Plot {$p->plot->plot_no} ({$p->project->name})", 'mode' => Payment::MODES[$p->mode], 'in' => (float) $p->amount, 'out' => 0.0, 'sort' => $p->id,
        ]);
        $exp = Expense::with(['project', 'category'])->whereBetween('spent_on', [$from, $to])->get()->map(fn ($e) => [
            'date' => $e->spent_on, 'txn' => $e->transaction_no, 'particulars' => ($e->category?->name ?? 'Expense').' — '.$e->description.($e->project ? " ({$e->project->name})" : ''), 'mode' => $e->mode ? (Payment::MODES[$e->mode] ?? $e->mode) : '', 'in' => 0.0, 'out' => (float) $e->amount, 'sort' => 1000000 + $e->id,
        ]);
        $rows = $income->concat($exp)->sortBy([['date', 'asc'], ['sort', 'asc']])->values();
        $bal = 0;
        $rows = $rows->map(function ($r) use (&$bal) {
            $bal += $r['in'] - $r['out'];

            return $r + ['balance' => $bal];
        });
        if ($request->query('export') === 'xlsx') {
            return Excel::download('day-book.xlsx', ['Date', 'Txn', 'Particulars', 'Mode', 'Receipts', 'Payments', 'Balance'],
                $rows->map(fn ($r) => [$r['date'], $r['txn'], $r['particulars'], $r['mode'], $r['in'] ?: '', $r['out'] ?: '', $r['balance']]), 'Day book '.$from->format('d-m-Y').' to '.$to->format('d-m-Y'));
        }

        return view('ws.accounts.daybook', compact('rows', 'from', 'to'));
    }

    public function receivables(Request $request)
    {
        $q = Instalment::with(['sale.plot', 'sale.customer', 'sale.project'])->where('status', '!=', 'paid')
            ->whereHas('sale', fn ($s) => $s->where('status', 'sale_init')->when($request->query('project'), fn ($w, $p) => $w->where('project_id', $p)))
            ->when($request->query('overdue'), fn ($q) => $q->whereDate('due_date', '<', today()))
            ->orderBy('due_date');
        $rows = $q->get();
        if ($request->query('export') === 'xlsx') {
            return Excel::download('receivables.xlsx', ['Due date', 'Days overdue', 'Sale', 'Project', 'Plot', 'Customer', 'Mobile', 'Instalment', 'Amount', 'Paid', 'Balance'],
                $rows->map(fn ($i) => [$i->due_date, $i->due_date->isPast() ? (int) $i->due_date->diffInDays(today()) : 0, $i->sale->sale_no, $i->sale->project->name, $i->sale->plot->plot_no, $i->sale->customer->name, $i->sale->customer->mobile, $i->name, (float) $i->amount, (float) $i->paid_amount, $i->balance()]), 'Receivables');
        }

        return view('ws.accounts.receivables', ['rows' => $rows, 'projects' => Project::launched()->orderBy('name')->pluck('name', 'id')]);
    }
}
