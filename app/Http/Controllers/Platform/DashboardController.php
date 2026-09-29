<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Platform overview (section 3A.3). Aggregates only — no customer-level data.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $active = Subscription::query()->whereIn('status', ['active', 'past_due'])->with('plan')->get();
        $mrr = $active->sum(fn ($s) => $s->cycle === 'yearly' ? (float) $s->plan->price_yearly / 12 : (float) $s->plan->price_monthly);

        $months = collect(range(11, 0))->map(function ($ago) {
            $start = now()->startOfMonth()->subMonths($ago);

            return [
                'label' => $start->format('M'),
                'value' => (float) SubscriptionInvoice::query()->where('status', 'paid')
                    ->whereBetween('paid_at', [$start, $start->copy()->endOfMonth()])->sum('amount'),
            ];
        })->all();

        return view('platform.dashboard', [
            'mrr' => $mrr,
            'statusCounts' => Tenant::query()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all(),
            'newThisMonth' => Tenant::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'trialsEnding' => Subscription::query()->where('status', SubscriptionStatus::Trial)->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->count(),
            'failedPayments' => SubscriptionInvoice::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->get(),
            'planMix' => Plan::query()->withCount('tenants')->orderBy('sort_order')->get(),
            'tenants' => Tenant::query()->with('plan')->latest()->limit(8)->get(),
            'revenue' => $months,
            'plotTotals' => Plot::acrossTenants()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all(),
            'health' => [
                'queue' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
                'notifications_failed' => DB::table('notification_logs')->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            ],
        ]);
    }
}
