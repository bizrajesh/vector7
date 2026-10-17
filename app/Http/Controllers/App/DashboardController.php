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
        if (! $request->user()->hasPerm('subscriptions.view')) {
            return $this->support($request); // App Support: the work queue, not business figures
        }
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

    /** App Support home: open tickets, new enquiries and service requests, today's chats, failed sign-ins. */
    private function support(Request $request)
    {
        return app(Tenancy::class)->withoutScope(function () use ($request) {
            $u = $request->user();
            $open = \App\Models\Ticket::whereNotIn('status', ['resolved', 'closed']);

            return view('app.support-dashboard', [
                'tiles' => array_filter([
                    $u->hasPerm('helpdesk.view') ? ['Open tickets', (clone $open)->count(), route('app.tickets.index', ['status' => 'open_all']), 'lifebuoy'] : null,
                    $u->hasPerm('helpdesk.view') ? ['Overdue tickets (SLA)', (clone $open)->where('sla_due_at', '<', now())->count(), route('app.tickets.index', ['overdue' => 1]), 'clock'] : null,
                    $u->hasPerm('helpdesk.view') ? ['Unassigned tickets', (clone $open)->whereNull('assignee_id')->count(), route('app.tickets.index', ['assignee' => 'none', 'status' => 'open_all']), 'user'] : null,
                    $u->hasPerm('app_enquiries.view') ? ['New enquiries', \App\Models\Enquiry::where('status', 'new')->count(), route('app.enquiries.index', ['status' => 'new']), 'chat'] : null,
                    $u->hasPerm('services.view') ? ['New service requests', \App\Models\ServiceRequest::where('status', 'new')->count(), route('app.services.index'), 'briefcase'] : null,
                    $u->hasPerm('app_chats.view') ? ['Chatbot conversations today', \App\Models\ChatConversation::where('created_at', '>=', today())->count(), route('app.chats.index'), 'chat'] : null,
                    $u->hasPerm('app_security.view') ? ['Failed sign-ins today', \App\Models\LoginEvent::whereIn('outcome', ['failed', 'locked', 'blocked'])->where('created_at', '>=', today())->count(), route('app.security.index', ['tab' => 'activity', 'outcome' => 'problem', 'from' => today()->toDateString()]), 'shield'] : null,
                    $u->hasPerm('app_security.view') ? ['Locked accounts', \App\Models\User::withoutGlobalScopes()->where('locked_until', '>', now())->count() + \App\Models\Customer::where('locked_until', '>', now())->count(), route('app.security.index', ['tab' => 'locked']), 'lock'] : null,
                ]),
                'mine' => $u->hasPerm('helpdesk.view') ? (clone $open)->where('assignee_id', $u->id)->orderBy('sla_due_at')->take(10)->get() : collect(),
            ]);
        });
    }
}
