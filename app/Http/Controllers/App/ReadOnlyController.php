<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Project;
use App\Models\Sale;
use App\Models\Tenant;
use App\Support\Excel;
use Illuminate\Http\Request;

/** App workspace read-only views across all tenants: projects (with stage progress) and live bookings & sales. */
class ReadOnlyController extends Controller
{
    public function projects(Request $request)
    {
        $q = Project::with('tenantRel:id,name,code')->latest('id')
            ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('project_code', 'like', "%$s%")))
            ->when($request->query('location'), fn ($q, $l) => $q->where('location', $l))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s));
        $this->timeline($q, $request, 'created_at');
        if ($request->query('export') === 'xlsx') {
            return Excel::download('projects-all-tenants.xlsx', ['Tenant', 'Code', 'Project', 'Approval', 'Location', 'District', 'Acres', 'Status', 'Progress %', 'Start', 'Est. end', 'Created'],
                $q->get()->map(fn ($p) => [$p->tenantRel?->name, $p->project_code, $p->name, $p->approval_type, $p->location, $p->district, (float) $p->size_acres, $p->statusLabel(), (float) $p->progress_pct, $p->start_date, $p->est_end_date, $p->created_at]), 'Projects');
        }

        return view('app.readonly.projects', [
            'projects' => $q->paginate(25)->withQueryString(),
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id'),
            'locations' => Project::distinct()->orderBy('location')->pluck('location'),
        ]);
    }

    public function project(Project $project)
    {
        $project->load(['tenantRel', 'stages', 'estimate', 'manager:id,name']);
        $plots = $project->plots()->selectRaw('status, count(*) as n, sum(size_sqft) as sqft')->groupBy('status')->get()->keyBy('status');

        return view('app.readonly.project', ['p' => $project, 'plots' => $plots]);
    }

    public function sales(Request $request)
    {
        $tab = $request->query('tab') === 'bookings' ? 'bookings' : 'sales';
        $model = $tab === 'bookings' ? Booking::query() : Sale::query();
        $date = $tab === 'bookings' ? 'booked_on' : 'started_on';
        $q = $model->with(['tenant:id,name', 'project:id,name,location', 'plot:id,plot_no,size_sqft', 'customer:id,name,mobile'])->latest('id')
            ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('location'), fn ($q, $l) => $q->whereHas('project', fn ($p) => $p->where('location', $l)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s));
        $this->timeline($q, $request, $date);
        $totals = (clone $q)->reorder()->selectRaw('count(*) as n, coalesce(sum(net_price),0) as value'.($tab === 'sales' ? ', coalesce(sum(paid_amount),0) as paid, coalesce(sum(due_amount),0) as due' : ''))->first();

        if ($request->query('export') === 'xlsx') {
            $rows = $q->get()->map(fn ($r) => $tab === 'bookings'
                ? [$r->tenant?->name, $r->booking_no, $r->project?->name, $r->project?->location, $r->plot?->plot_no, $r->customer?->name, (float) $r->net_price, $r->booked_on, $r->valid_till, Booking::STATUSES[$r->status] ?? $r->status]
                : [$r->tenant?->name, $r->sale_no, $r->project?->name, $r->project?->location, $r->plot?->plot_no, $r->customer?->name, (float) $r->net_price, (float) $r->paid_amount, (float) $r->due_amount, $r->started_on, Sale::STATUSES[$r->status] ?? $r->status]);
            $headers = $tab === 'bookings'
                ? ['Tenant', 'Booking', 'Project', 'Location', 'Plot', 'Customer', 'Net price', 'Booked on', 'Valid till', 'Status']
                : ['Tenant', 'Sale', 'Project', 'Location', 'Plot', 'Customer', 'Net price', 'Paid', 'Due', 'Started', 'Status'];

            return Excel::download("$tab-all-tenants.xlsx", $headers, $rows, ucfirst($tab));
        }

        return view('app.readonly.sales', [
            'tab' => $tab,
            'rows' => $q->paginate(25)->withQueryString(),
            'totals' => $totals,
            'statuses' => $tab === 'bookings' ? Booking::STATUSES : Sale::STATUSES,
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id'),
            'projects' => Project::when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))->orderBy('name')->pluck('name', 'id'),
            'locations' => Project::distinct()->orderBy('location')->pluck('location'),
        ]);
    }

    /** Timeline filter: preset period or from/to dates. */
    private function timeline($q, Request $request, string $column): void
    {
        $from = $request->date('from');
        $to = $request->date('to');
        switch ($request->query('period')) {
            case 'month': $from = now()->startOfMonth(); break;
            case 'quarter': $from = now()->startOfQuarter(); break;
            case 'year': $from = now()->month >= 4 ? now()->setDate(now()->year, 4, 1) : now()->setDate(now()->year - 1, 4, 1); break;
        }
        $q->when($from, fn ($q) => $q->whereDate($column, '>=', $from))->when($to, fn ($q) => $q->whereDate($column, '<=', $to));
    }
}
