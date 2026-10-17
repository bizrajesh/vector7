<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\PlanLimiter;
use App\Services\WorkspaceAlerts;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;
        if ($user->isLister()) {
            return $this->lister($user, $tenant);
        }
        // Sections follow the role; data for modules the plan does not include is skipped.
        $sections = array_values(array_filter(DashboardService::sectionsFor($user), fn ($s) => match ($s) {
            'account' => PlanLimiter::moduleEnabled($tenant, 'accounts') && $user->hasPerm('accounts.view'),
            'sales' => $user->hasPerm('bookings.view') || $user->hasPerm('sales.view'),
            'manager' => $user->hasPerm('projects.view'),
            'admin' => $user->hasPerm('projects.view'),
            'support' => $user->hasPerm('enquiries.view') || $user->hasPerm('tickets.view'),
            default => false,
        }));
        $data = [];
        foreach ($sections as $s) {
            $data[$s] = DashboardService::{$s}();
        }

        return view('ws.dashboard.index', ['user' => $user, 'tenant' => $tenant, 'sections' => $sections, 'd' => $data, 'alerts' => WorkspaceAlerts::for($user)]);
    }

    /** Property lister: how every listing is doing — views, enquiries, call-back requests, buyers who unlocked the number. */
    private function lister($user, $tenant)
    {
        $listings = \App\Models\Listing::with('cover')->latest('id')->get();
        $leadCounts = \App\Models\LeadInterest::whereNotNull('listing_id')->selectRaw('listing_id, count(distinct lead_id) n')->groupBy('listing_id')->pluck('n', 'listing_id');
        $unlocks = \App\Models\ContactUnlock::where('tenant_id', $tenant->id)->where('status', 'paid')->whereNotNull('listing_id')
            ->selectRaw('listing_id, count(*) n')->groupBy('listing_id')->pluck('n', 'listing_id');
        $leads = \App\Models\Lead::with(['interests.listing', 'stage'])->latest('id')->take(8)->get();

        return view('ws.dashboard.lister', [
            'user' => $user, 'tenant' => $tenant, 'listings' => $listings, 'leadCounts' => $leadCounts, 'unlocks' => $unlocks, 'leads' => $leads,
            'stats' => [
                'live' => $listings->filter->isLive()->count(),
                'draft' => $listings->where('status', 'draft')->count(),
                'views' => (int) $listings->sum('views'),
                'enquiries' => \App\Models\Lead::count(),
                'callbacks' => \App\Models\Lead::where('source', 'callback')->whereNotIn('lead_stage_id', \App\Models\LeadStage::where(fn ($q) => $q->where('is_won', true)->orWhere('is_lost', true))->pluck('id'))->count(),
                'unlocks' => (int) $unlocks->sum(),
            ],
            'usage' => PlanLimiter::usage($tenant)['listings'],
            'sub' => $tenant->subscription,
        ]);
    }

    public function notifications(Request $request)
    {
        return view('ws.notifications', ['alerts' => WorkspaceAlerts::for($request->user())]);
    }
}
