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

    public function notifications(Request $request)
    {
        return view('ws.notifications', ['alerts' => WorkspaceAlerts::for($request->user())]);
    }
}
