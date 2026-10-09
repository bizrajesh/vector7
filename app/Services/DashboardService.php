<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\Instalment;
use App\Models\Payment;
use App\Models\Plot;
use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Numbers and chart configs for the role dashboards (tenant) and the App dashboards. */
class DashboardService
{
    public const NAVY = '#0B1B33';

    public const TEAL = '#0F8F84';

    public const GOLD = '#D97706';

    public const RED = '#DC2626';

    /** Which tenant dashboard sections a user sees, from the role's base role. */
    public static function sectionsFor(User $user): array
    {
        return match ($user->baseRole()) {
            'tenant_admin' => ['admin', 'manager', 'sales', 'account', 'support'],
            'tenant_manager' => ['manager', 'admin'],
            'tenant_sales' => ['sales', 'support'],
            'tenant_account' => ['account'],
            'support' => ['support'],
            default => ['support'],
        };
    }

    /** Last N months as ['Y-m' => 'Mon YY']. */
    public static function months(int $n = 6, ?Carbon $end = null): array
    {
        $end = ($end ?? now())->copy()->startOfMonth();
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $m = $end->copy()->subMonths($i);
            $out[$m->format('Y-m')] = $m->format('M y');
        }

        return $out;
    }

    private static function byMonth(Collection $items, string $dateField, array $months, ?callable $value = null): array
    {
        $g = $items->groupBy(fn ($i) => Carbon::parse($i->{$dateField})->format('Y-m'));

        return array_map(fn ($k) => round((float) ($value ? ($g[$k] ?? collect())->sum($value) : ($g[$k] ?? collect())->count()), 2), array_keys($months));
    }

    // ---------------------------------------------------------------- tenant

    public static function admin(): array
    {
        $projects = Project::with(['estimate', 'plots', 'stages'])->get();
        $byStatus = $projects->countBy('status');
        $statusKeys = array_keys(array_filter(Project::STATUSES, fn ($k) => ($byStatus[$k] ?? 0) > 0, ARRAY_FILTER_USE_KEY));
        $withBudget = $projects->filter(fn ($p) => $p->estimate)->take(8);
        $launched = $projects->where('status', 'launched');
        $plots = $launched->flatMap->plots;
        $months = self::months();
        $pays = Payment::whereDate('paid_on', '>=', array_key_first($months).'-01')->get(['paid_on', 'amount']);
        $sales = Sale::whereNotIn('status', ['refunded'])->get(['net_price', 'paid_amount', 'started_on']);

        return [
            'stats' => [
                'active' => $projects->whereIn('status', ['init', 'go_no_go', 'in_progress', 'ready_to_launch'])->count(),
                'launched' => $launched->count(),
                'available' => $plots->where('status', 'available')->count(),
                'salesValue' => (float) $sales->sum('net_price'),
                'collected' => (float) $sales->sum('paid_amount'),
                'overdue' => self::overdueAmount(),
            ],
            'statusChart' => ['type' => 'doughnut', 'labels' => array_map(fn ($k) => Project::STATUSES[$k], $statusKeys),
                'datasets' => [['data' => array_map(fn ($k) => $byStatus[$k], $statusKeys), 'backgroundColor' => ['#94A3B8', '#64748B', self::GOLD, '#2563EB', '#7C3AED', self::TEAL, self::NAVY]]]],
            'budgetChart' => ['type' => 'bar', 'money' => true, 'labels' => $withBudget->pluck('name')->values(), 'datasets' => [
                ['label' => 'Budget', 'data' => $withBudget->map(fn ($p) => (float) $p->estimate->total_cost)->values(), 'backgroundColor' => '#C9D0DB'],
                ['label' => 'Spent', 'data' => $withBudget->map(fn ($p) => ProjectService::projectSpend($p))->values(), 'backgroundColor' => self::TEAL],
            ]],
            'inventoryChart' => ['type' => 'bar', 'stacked' => true, 'horizontal' => true, 'labels' => $launched->pluck('name')->values(),
                'datasets' => collect(Plot::STATUSES)->map(fn ($label, $s) => ['label' => $label, 'data' => $launched->map(fn ($p) => $p->plots->where('status', $s)->count())->values(), 'backgroundColor' => Plot::COLORS[$s]])
                    ->filter(fn ($d) => collect($d['data'])->sum() > 0)->values()],
            'collectionChart' => ['type' => 'bar', 'money' => true, 'labels' => array_values($months), 'datasets' => [
                ['label' => 'Collected', 'data' => self::byMonth($pays, 'paid_on', $months, fn ($p) => $p->amount), 'backgroundColor' => self::NAVY],
            ]],
        ];
    }

    public static function manager(): array
    {
        $tenant = app(Tenancy::class)->get();
        $alertPct = (float) ($tenant?->setting()->budget_alert_pct ?? 80);
        $running = Project::whereIn('status', ['in_progress', 'ready_to_launch'])->with(['stages', 'estimate'])->orderBy('name')->get();
        $budget = Project::whereHas('estimate')->with(['estimate', 'stages'])->whereNotIn('status', ['closed', 'draft'])->get()
            ->map(fn ($p) => ['project' => $p, 'budget' => (float) $p->estimate->total_cost, 'spent' => ProjectService::projectSpend($p)])
            ->filter(fn ($r) => $r['budget'] > 0 && $r['spent'] / $r['budget'] * 100 >= $alertPct)->values();

        return [
            'running' => $running,
            'delayed' => ProjectSubtask::with(['project', 'stage'])->whereNotIn('status', ['done'])->whereDate('planned_end', '<', today())
                ->whereHas('project', fn ($q) => $q->where('status', 'in_progress'))->orderBy('planned_end')->take(12)->get(),
            'delayedCount' => ProjectSubtask::whereNotIn('status', ['done'])->whereDate('planned_end', '<', today())->whereHas('project', fn ($q) => $q->where('status', 'in_progress'))->count(),
            'budgetAlerts' => $budget,
            'alertPct' => $alertPct,
        ];
    }

    public static function sales(): array
    {
        $months = self::months();
        $since = Carbon::parse(array_key_first($months).'-01');
        $bookings = Booking::whereDate('booked_on', '>=', $since)->get(['booked_on', 'status']);
        $total90 = Booking::whereDate('booked_on', '>=', today()->subDays(90))->count();
        $conv90 = Booking::whereDate('booked_on', '>=', today()->subDays(90))->where('status', 'converted')->count();

        return [
            'expiring' => Booking::with(['plot', 'customer', 'project'])->where('status', 'active')->whereDate('valid_till', '<=', today()->addDays(3))->orderBy('valid_till')->take(10)->get(),
            'due' => Instalment::with(['sale.customer', 'sale.plot'])->where('status', '!=', 'paid')->whereHas('sale', fn ($q) => $q->where('status', 'sale_init'))
                ->whereDate('due_date', '<=', today()->addDays(7))->orderBy('due_date')->take(10)->get(),
            'enquiries' => Enquiry::whereIn('status', ['new', 'contacted'])->count(),
            'newEnquiries' => Enquiry::where('status', 'new')->count(),
            'activeBookings' => Booking::where('status', 'active')->count(),
            'conversion' => $total90 ? round($conv90 / $total90 * 100) : 0,
            'bookingChart' => ['type' => 'bar', 'labels' => array_values($months), 'datasets' => [
                ['label' => 'Bookings', 'data' => self::byMonth($bookings, 'booked_on', $months), 'backgroundColor' => self::GOLD],
                ['label' => 'Converted to sale', 'data' => self::byMonth($bookings->where('status', 'converted'), 'booked_on', $months), 'backgroundColor' => self::TEAL],
            ]],
        ];
    }

    public static function account(): array
    {
        $months = self::months();
        $since = array_key_first($months).'-01';
        $pays = Payment::whereDate('paid_on', '>=', $since)->get(['paid_on', 'amount']);
        $exps = Expense::whereDate('spent_on', '>=', $since)->get(['spent_on', 'amount']);

        return [
            'today' => (float) Payment::whereDate('paid_on', today())->sum('amount'),
            'month' => (float) Payment::whereDate('paid_on', '>=', now()->startOfMonth())->sum('amount'),
            'expMonth' => (float) Expense::whereDate('spent_on', '>=', now()->startOfMonth())->sum('amount'),
            'receivable' => (float) Instalment::where('status', '!=', 'paid')->whereHas('sale', fn ($q) => $q->where('status', 'sale_init'))->get()->sum(fn ($i) => $i->balance()),
            'overdue' => self::overdueAmount(),
            'cashChart' => ['type' => 'bar', 'money' => true, 'labels' => array_values($months), 'datasets' => [
                ['label' => 'Received', 'data' => self::byMonth($pays, 'paid_on', $months, fn ($p) => $p->amount), 'backgroundColor' => self::TEAL],
                ['label' => 'Spent', 'data' => self::byMonth($exps, 'spent_on', $months, fn ($e) => $e->amount), 'backgroundColor' => self::GOLD],
            ]],
        ];
    }

    public static function support(): array
    {
        return [
            'enquiries' => Enquiry::with('project')->whereIn('status', ['new', 'contacted'])->latest()->take(8)->get(),
            'enquiryCount' => Enquiry::whereIn('status', ['new', 'contacted'])->count(),
            'tickets' => Ticket::whereNotIn('status', ['resolved', 'closed'])->orderByRaw('sla_due_at is null, sla_due_at')->take(8)->get(),
            'ticketCount' => Ticket::whereNotIn('status', ['resolved', 'closed'])->count(),
            'breached' => Ticket::whereNotIn('status', ['resolved', 'closed'])->where('sla_due_at', '<', now())->count(),
        ];
    }

    public static function overdueAmount(): float
    {
        return (float) Instalment::where('status', '!=', 'paid')->whereDate('due_date', '<', today())
            ->whereHas('sale', fn ($q) => $q->where('status', 'sale_init'))->get()->sum(fn ($i) => $i->balance());
    }

    // ---------------------------------------------------------------- App (cross-tenant; runs without the tenant scope)

    /** @param array{tenant?:int|string|null, location?:string|null, from?:string|null, to?:string|null} $f */
    public static function app(array $f): array
    {
        return app(Tenancy::class)->withoutScope(function () use ($f) {
            $from = ! empty($f['from']) ? Carbon::parse($f['from'])->startOfDay() : now()->subMonths(5)->startOfMonth();
            $to = ! empty($f['to']) ? Carbon::parse($f['to'])->endOfDay() : now()->endOfDay();
            $tenantId = $f['tenant'] ?? null;
            $location = $f['location'] ?? null;
            $months = self::monthsBetween($from, $to);
            $scopeProject = fn ($q) => $q->when($tenantId, fn ($w) => $w->where('tenant_id', $tenantId))
                ->when($location, fn ($w) => $w->where(fn ($x) => $x->where('location', $location)->orWhere('district', $location)));
            $projectIds = $tenantId || $location ? Project::withoutGlobalScopes()->where($scopeProject)->pluck('id') : null;
            $byProject = fn ($q) => $q->when($projectIds !== null, fn ($w) => $w->whereIn('project_id', $projectIds));

            // Operational
            $tenants = Tenant::with(['subscription.plan'])->get();
            $subs = $tenants->pluck('subscription')->filter();
            $paidSubs = $subs->where('status', 'active');
            $mrr = $paidSubs->sum(fn ($s) => $s->plan ? ($s->plan->billing_cycle === 'yearly' ? (float) $s->plan->price / 12 : (float) $s->plan->price) : 0);
            $usage = $tenants->map(function (Tenant $t) {
                $rows = collect(PlanLimiter::usage($t));
                $worst = $rows->max('pct') ?? 0;

                return ['tenant' => $t, 'rows' => $rows, 'worst' => $worst, 'storage' => $rows->get('storage_mb')];
            });

            // Customer
            $customers = Customer::query();
            $signups = Customer::whereBetween('created_at', [$from, $to])->get(['created_at']);

            // Projects / bookings / sales
            $projects = Project::withoutGlobalScopes()->where($scopeProject)->whereNull('deleted_at')->get(['id', 'status', 'tenant_id']);
            $bookings = Booking::withoutGlobalScopes()->where($byProject)->whereBetween('booked_on', [$from, $to])->get(['booked_on', 'status', 'net_price']);
            $sales = Sale::withoutGlobalScopes()->where($byProject)->whereBetween('started_on', [$from, $to])->get(['started_on', 'status', 'net_price', 'paid_amount']);
            $byStatus = $projects->countBy('status');

            return [
                'months' => $months,
                'op' => [
                    'tenants' => $tenants->count(),
                    'activeTenants' => $tenants->where('status', 'active')->count(),
                    'projects' => $projects->count(),
                    'launched' => $byStatus['launched'] ?? 0,
                    'openTickets' => Ticket::withoutGlobalScopes()->whereNotIn('status', ['resolved', 'closed'])->count(),
                    'newEnquiries' => Enquiry::withoutGlobalScopes()->where('status', 'new')->count(),
                    'failedJobs' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
                    'queued' => \Illuminate\Support\Facades\DB::table('jobs')->count(),
                ],
                'sub' => [
                    'mrr' => $mrr,
                    'active' => $paidSubs->count(),
                    'trial' => $subs->where('status', 'trial')->count(),
                    'renewals' => $subs->filter(fn ($s) => $s->ends_on && $s->ends_on->between(today(), today()->addDays(30)))->count(),
                    'storageUsed' => $usage->sum(fn ($u) => (float) ($u['storage']['used'] ?? 0)),
                    'storageLimit' => $usage->sum(fn ($u) => max(0, (float) ($u['storage']['limit'] ?? 0))),
                    'near' => $usage->filter(fn ($u) => $u['worst'] >= 80)->sortByDesc('worst')->values(),
                    'planChart' => ['type' => 'doughnut', 'labels' => $subs->groupBy(fn ($s) => $s->plan?->name ?? '—')->keys()->values(),
                        'datasets' => [['data' => $subs->groupBy(fn ($s) => $s->plan?->name ?? '—')->map->count()->values(), 'backgroundColor' => [self::NAVY, self::TEAL, self::GOLD, '#7C3AED', '#94A3B8']]]],
                ],
                'cust' => [
                    'total' => $customers->count(),
                    'active' => Customer::where('is_active', true)->where('last_login_at', '>=', now()->subDays(30))->count(),
                    'new' => $signups->count(),
                    'chart' => ['type' => 'line', 'labels' => array_values($months), 'datasets' => [['label' => 'Sign-ups', 'data' => self::byMonth($signups, 'created_at', $months), 'borderColor' => self::TEAL, 'backgroundColor' => self::TEAL]]],
                ],
                'proj' => [
                    'chart' => ['type' => 'bar', 'labels' => array_values(Project::STATUSES), 'datasets' => [['label' => 'Projects', 'data' => array_map(fn ($k) => $byStatus[$k] ?? 0, array_keys(Project::STATUSES)), 'backgroundColor' => self::NAVY]]],
                ],
                'sales' => [
                    'bookings' => $bookings->count(),
                    'bookingValue' => (float) $bookings->sum('net_price'),
                    'sales' => $sales->count(),
                    'salesValue' => (float) $sales->where('status', '!=', 'refunded')->sum('net_price'),
                    'collected' => (float) $sales->sum('paid_amount'),
                    'conversion' => $bookings->count() ? round($bookings->where('status', 'converted')->count() / $bookings->count() * 100) : 0,
                    'chart' => ['type' => 'bar', 'money' => true, 'labels' => array_values($months), 'datasets' => [
                        ['label' => 'Booking value', 'data' => self::byMonth($bookings, 'booked_on', $months, fn ($b) => $b->net_price), 'backgroundColor' => self::GOLD],
                        ['label' => 'Sales value', 'data' => self::byMonth($sales->where('status', '!=', 'refunded'), 'started_on', $months, fn ($s) => $s->net_price), 'backgroundColor' => self::TEAL],
                    ]],
                ],
            ];
        });
    }

    public static function monthsBetween(Carbon $from, Carbon $to): array
    {
        $out = [];
        $m = $from->copy()->startOfMonth();
        $guard = 0;
        while ($m->lte($to) && $guard++ < 36) {
            $out[$m->format('Y-m')] = $m->format('M y');
            $m->addMonth();
        }

        return $out;
    }
}
