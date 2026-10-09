<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceAlerts;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('ws.dashboard.basic', ['user' => $request->user()]);
    }

    public function notifications(Request $request)
    {
        return view('ws.notifications', ['alerts' => WorkspaceAlerts::for($request->user())]);
    }
}
