<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Layout;
use App\Models\Sale;
use App\Models\SaleInstalment;
use App\Models\SharePool;
use App\Models\User;
use App\Services\Analytics;
use App\Services\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Business Analytics (DSS, section 8.1). */
class AnalyticsController extends Controller
{
    public function __invoke(Request $request, Analytics $analytics, PlanLimits $limits): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('analytics.view') || $user->hasPermission('analytics.view.sales'), 403);
        abort_unless($limits->feature('dss') || $user->hasPermission('analytics.view'), 403, 'Business analytics is not included in your plan.');

        $request->validate([
            'layout_id' => ['nullable', 'integer'],
            'period' => ['nullable', Rule::in(['month', 'quarter', 'year'])],
        ]);
        $salesOnly = ! $user->hasPermission('analytics.view');

        $ageing = SaleInstalment::query()->whereIn('status', ['due', 'partial'])
            ->whereHas('sale', fn ($q) => $q->where('status', 'ongoing'))
            ->where('due_date', '<', now()->toDateString())
            ->get()
            ->groupBy(fn ($i) => match (true) {
                $i->due_date->diffInDays(now()) <= 7 => '0–7 days',
                $i->due_date->diffInDays(now()) <= 15 => '8–15 days',
                default => '15+ days',
            })->map(fn ($g) => $g->sum(fn ($i) => $i->balance()));

        return view('app.analytics', [
            'd' => $analytics->adminDashboard($request->integer('layout_id') ?: null, $request->input('period', 'year')),
            'layouts' => Layout::query()->orderBy('name')->get(['id', 'name']),
            'ageing' => $ageing,
            'salesTeam' => User::query()->inCurrentTenant()->where('role', 'sales')->get()->map(fn ($u) => [
                'name' => $u->name,
                'sales' => Sale::query()->where('created_by', $u->id)->whereIn('status', ['ongoing', 'paid', 'registered'])->count(),
                'value' => (float) Sale::query()->where('created_by', $u->id)->whereIn('status', ['ongoing', 'paid', 'registered'])->sum('sale_value'),
            ]),
            'pools' => $salesOnly ? collect() : SharePool::query()->with(['layout', 'events'])->get(),
            'salesOnly' => $salesOnly,
        ]);
    }
}
