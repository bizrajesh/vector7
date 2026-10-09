<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Tenant;
use App\Services\DashboardService;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** App dashboards: operational, subscription, customer, project, booking & sales — filter by tenant, location, timeline. */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'tenant' => 'nullable|integer',
            'location' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        $locations = app(Tenancy::class)->withoutScope(fn () => Project::withoutGlobalScopes()->whereNotNull('district')->distinct()->orderBy('district')->pluck('district'));

        return view('app.dashboard', [
            'd' => DashboardService::app($f),
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id'),
            'locations' => $locations->combine($locations),
        ]);
    }
}
