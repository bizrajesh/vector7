<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Broker;
use App\Models\Expense;
use App\Models\Instalment;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Refund;
use App\Models\Registration;
use App\Models\Sale;
use Carbon\Carbon;

/**
 * Tenant reports. Every report returns the same shape so one screen, one Excel writer and one PDF view serve all:
 * ['headers' => [...], 'rows' => [[...]], 'money' => [col indexes], 'dates' => [col indexes], 'totals' => [...]|null].
 * Queries run inside the tenant scope (TenantScope), so a report can never read another tenant's data.
 */
class ReportService
{
    /** key => [title, description, filters, icon] */
    public const REPORTS = [
        'project-progress' => ['Project progress', 'Status, stage completion, timeline and spend for every project.', ['status'], 'folder'],
        'estimate-vs-actual' => ['Estimate vs actual', 'Budget from the estimate against actual spend, by project.', ['project'], 'scale'],
        'plot-inventory' => ['Plot inventory', 'Plot count and value by status for each launched layout.', ['project'], 'grid'],
        'bookings' => ['Bookings', 'All bookings with status, hold period and price.', ['project', 'status', 'from', 'to'], 'calendar'],
        'sales-collections' => ['Sales & collections', 'Sale value, money collected and balance due per sale.', ['project', 'status', 'from', 'to'], 'cart'],
        'dues' => ['Dues & overdue', 'Unpaid instalments, with days overdue.', ['project', 'overdue'], 'clock'],
        'refunds' => ['Refunds', 'Refund requests with penalty and amount refunded.', ['project', 'status', 'from', 'to'], 'refresh'],
        'expenses' => ['Expenses by category', 'Spend grouped by project and category.', ['project', 'from', 'to'], 'receipt'],
        'broker-commission' => ['Broker commission', 'Sales and commission earned per broker.', ['from', 'to'], 'briefcase'],
        'registration-status' => ['Registration status', 'Where each registration stands, SRO and document numbers.', ['project', 'status'], 'clipboard'],
    ];

    public static function statusOptions(string $key): array
    {
        return match ($key) {
            'project-progress' => Project::STATUSES,
            'bookings' => Booking::STATUSES,
            'sales-collections' => Sale::STATUSES,
            'refunds' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'],
            'registration-status' => Registration::STATUSES,
            default => [],
        };
    }

    public static function run(string $key, array $f): array
    {
        $from = ! empty($f['from']) ? Carbon::parse($f['from'])->startOfDay() : null;
        $to = ! empty($f['to']) ? Carbon::parse($f['to'])->endOfDay() : null;
        $project = $f['project'] ?? null;
        $status = $f['status'] ?? null;
        $range = fn ($q, string $col) => $q->when($from, fn ($w) => $w->where($col, '>=', $from))->when($to, fn ($w) => $w->where($col, '<=', $to));

        return match ($key) {
            'project-progress' => self::projectProgress($status),
            'estimate-vs-actual' => self::estimateVsActual($project),
            'plot-inventory' => self::plotInventory($project),
            'bookings' => self::bookings($range(Booking::with(['project', 'plot', 'customer'])->when($project, fn ($q) => $q->where('project_id', $project))->when($status, fn ($q) => $q->where('status', $status)), 'booked_on')),
            'sales-collections' => self::sales($range(Sale::with(['project', 'plot', 'customer'])->when($project, fn ($q) => $q->where('project_id', $project))->when($status, fn ($q) => $q->where('status', $status)), 'started_on')),
            'dues' => self::dues($project, ! empty($f['overdue'])),
            'refunds' => self::refunds($range(Refund::with(['sale.project', 'plot', 'customer'])->when($project, fn ($q) => $q->whereHas('sale', fn ($s) => $s->where('project_id', $project)))->when($status, fn ($q) => $q->where('status', $status)), 'created_at')),
            'expenses' => self::expenses($range(Expense::with(['project', 'category'])->when($project, fn ($q) => $q->where('project_id', $project)), 'spent_on')),
            'broker-commission' => self::brokers($from, $to),
            'registration-status' => self::registrations(Registration::with(['project', 'plot', 'customer', 'sro'])->when($project, fn ($q) => $q->where('project_id', $project))->when($status, fn ($q) => $q->where('status', $status))),
        };
    }

    private static function projectProgress(?string $status): array
    {
        $rows = Project::with(['stages', 'estimate', 'manager'])->when($status, fn ($q) => $q->where('status', $status))->orderBy('name')->get()->map(function (Project $p) {
            $done = $p->stages->where('status', 'done')->count();

            return [$p->project_code, $p->name, $p->location, $p->statusLabel(), $p->manager?->name, $p->start_date, $p->est_end_date,
                round((float) $p->progress_pct, 1).'%', $p->stages->count() ? "$done / {$p->stages->count()}" : '—', (float) ($p->estimate?->total_cost ?? 0), ProjectService::projectSpend($p)];
        });

        return ['headers' => ['Code', 'Project', 'Location', 'Status', 'Manager', 'Start', 'Est. end', 'Progress', 'Stages done', 'Budget', 'Spent'],
            'rows' => $rows->all(), 'money' => [9, 10], 'dates' => [5, 6],
            'totals' => ['Total', $rows->count().' projects', '', '', '', '', '', '', '', $rows->sum(9), $rows->sum(10)]];
    }

    private static function estimateVsActual(?string $project): array
    {
        $rows = Project::whereHas('estimate')->with(['estimate', 'stages'])->when($project, fn ($q) => $q->where('id', $project))->orderBy('name')->get()->map(function (Project $p) {
            $budget = (float) $p->estimate->total_cost;
            $spent = ProjectService::projectSpend($p);

            return [$p->name, ucfirst((string) $p->estimate->tier), (float) $p->estimate->facility_cost, (float) $p->estimate->stage_cost + (float) $p->estimate->other_cost, $budget, $spent, $budget - $spent, $budget > 0 ? round($spent / $budget * 100, 1).'%' : '—'];
        });

        return ['headers' => ['Project', 'Tier', 'Facilities budget', 'Stages & other budget', 'Total budget', 'Spent', 'Variance', 'Used'],
            'rows' => $rows->all(), 'money' => [2, 3, 4, 5, 6], 'dates' => [],
            'totals' => ['Total', '', $rows->sum(2), $rows->sum(3), $rows->sum(4), $rows->sum(5), $rows->sum(6), '']];
    }

    private static function plotInventory(?string $project): array
    {
        $statuses = array_keys(Plot::STATUSES);
        $rows = Project::whereHas('plots')->with('plots')->when($project, fn ($q) => $q->where('id', $project))->orderBy('name')->get()->map(function (Project $p) use ($statuses) {
            $by = $p->plots->countBy('status');
            $avail = $p->plots->where('status', 'available');

            return array_merge([$p->name, $p->plots->count()], array_map(fn ($s) => (int) ($by[$s] ?? 0), $statuses),
                [(float) $avail->sum('size_sqft'), $avail->sum(fn ($pl) => $pl->currentPrice())]);
        });
        $n = count($statuses);
        $totals = ['Total', $rows->sum(1)];
        foreach (range(2, $n + 3) as $i) {
            $totals[] = $rows->sum($i);
        }

        return ['headers' => array_merge(['Project', 'Plots'], array_values(Plot::STATUSES), ['Available sq ft', 'Available value']),
            'rows' => $rows->all(), 'money' => [$n + 3], 'dates' => [], 'totals' => $totals];
    }

    private static function bookings($q): array
    {
        $rows = $q->latest('booked_on')->get()->map(fn (Booking $b) => [$b->booking_no, $b->booked_on, $b->project->name, $b->plot->plot_no, $b->customer->name, $b->customer->mobile, Booking::STATUSES[$b->status] ?? $b->status, $b->valid_till, (float) $b->net_price]);

        return ['headers' => ['Booking', 'Booked on', 'Project', 'Plot', 'Customer', 'Mobile', 'Status', 'Held till', 'Net price'],
            'rows' => $rows->all(), 'money' => [8], 'dates' => [1, 7], 'totals' => ['Total', $rows->count().' bookings', '', '', '', '', '', '', $rows->sum(8)]];
    }

    private static function sales($q): array
    {
        $rows = $q->latest('started_on')->get()->map(fn (Sale $s) => [$s->sale_no, $s->started_on, $s->project->name, $s->plot->plot_no, $s->customer->name, Sale::STATUSES[$s->status] ?? $s->status, (float) $s->net_price, (float) $s->paid_amount, max(0, (float) $s->net_price - (float) $s->paid_amount)]);

        return ['headers' => ['Sale', 'Started', 'Project', 'Plot', 'Customer', 'Status', 'Net price', 'Collected', 'Balance'],
            'rows' => $rows->all(), 'money' => [6, 7, 8], 'dates' => [1], 'totals' => ['Total', $rows->count().' sales', '', '', '', '', $rows->sum(6), $rows->sum(7), $rows->sum(8)]];
    }

    private static function dues(?string $project, bool $overdueOnly): array
    {
        $rows = Instalment::with(['sale.plot', 'sale.customer', 'sale.project'])->where('status', '!=', 'paid')
            ->whereHas('sale', fn ($s) => $s->where('status', 'sale_init')->when($project, fn ($w) => $w->where('project_id', $project)))
            ->when($overdueOnly, fn ($q) => $q->whereDate('due_date', '<', today()))->orderBy('due_date')->get()
            ->map(fn (Instalment $i) => [$i->due_date, $i->due_date->lt(today()) ? (int) $i->due_date->diffInDays(today()) : 0, $i->sale->sale_no, $i->sale->project->name, $i->sale->plot->plot_no, $i->sale->customer->name, $i->sale->customer->mobile, $i->name, $i->balance()]);

        return ['headers' => ['Due date', 'Days overdue', 'Sale', 'Project', 'Plot', 'Customer', 'Mobile', 'Instalment', 'Balance'],
            'rows' => $rows->all(), 'money' => [8], 'dates' => [0], 'totals' => ['Total', '', '', '', '', '', '', '', $rows->sum(8)]];
    }

    private static function refunds($q): array
    {
        $rows = $q->latest()->get()->map(fn (Refund $r) => [$r->created_at, $r->sale->sale_no, $r->sale->project->name, $r->plot->plot_no, $r->customer->name, ucfirst($r->status), (int) $r->days_late, (float) $r->paid_amount, (float) $r->penalty_amount, (float) $r->refund_amount]);

        return ['headers' => ['Requested', 'Sale', 'Project', 'Plot', 'Customer', 'Status', 'Days late', 'Paid', 'Penalty', 'Refund'],
            'rows' => $rows->all(), 'money' => [7, 8, 9], 'dates' => [0], 'totals' => ['Total', '', '', '', '', '', '', $rows->sum(7), $rows->sum(8), $rows->sum(9)]];
    }

    private static function expenses($q): array
    {
        $rows = $q->get()->groupBy(fn ($e) => ($e->project?->name ?? 'General').'|'.($e->category?->name ?? 'Uncategorised'))
            ->map(function ($g, $k) {
                [$p, $c] = explode('|', $k, 2);

                return [$p, $c, $g->count(), $g->min('spent_on'), $g->max('spent_on'), (float) $g->sum('amount')];
            })->sortBy([[0, 'asc'], [5, 'desc']])->values();

        return ['headers' => ['Project', 'Category', 'Entries', 'First', 'Last', 'Amount'],
            'rows' => $rows->all(), 'money' => [5], 'dates' => [3, 4], 'totals' => ['Total', '', $rows->sum(2), '', '', $rows->sum(5)]];
    }

    private static function brokers(?Carbon $from, ?Carbon $to): array
    {
        $rows = Broker::orderBy('name')->get()->map(function (Broker $b) use ($from, $to) {
            $sales = Sale::where('broker_id', $b->id)->whereNotIn('status', ['refunded'])
                ->when($from, fn ($w) => $w->where('started_on', '>=', $from))->when($to, fn ($w) => $w->where('started_on', '<=', $to))->get();

            return [$b->name, $b->mobile, (float) $b->commission_pct.'%', $sales->count(), (float) $sales->sum('net_price'), (float) $sales->sum('broker_commission_amount')];
        })->values();

        return ['headers' => ['Broker', 'Mobile', 'Rate', 'Sales', 'Sales value', 'Commission'],
            'rows' => $rows->all(), 'money' => [4, 5], 'dates' => [], 'totals' => ['Total', '', '', $rows->sum(3), $rows->sum(4), $rows->sum(5)]];
    }

    private static function registrations($q): array
    {
        $rows = $q->latest('id')->get()->map(fn (Registration $r) => [$r->project->name, $r->plot->plot_no, $r->customer->name, Registration::STATUSES[$r->status] ?? $r->status, $r->sro?->name, $r->registration_date, $r->registered_doc_no, $r->registered_doc_date]);

        return ['headers' => ['Project', 'Plot', 'Buyer', 'Status', 'SRO', 'Registration date', 'Document no.', 'Document date'],
            'rows' => $rows->all(), 'money' => [], 'dates' => [5, 7], 'totals' => null];
    }

    /** Format one cell for screen / PDF. */
    public static function cell(array $report, int $i, $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (in_array($i, $report['money'], true) && is_numeric($v)) {
            return \App\Support\Format::inr((float) $v);
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d M Y');
        }

        return is_numeric($v) ? \App\Support\Format::indian((float) $v) : (string) $v;
    }
}
