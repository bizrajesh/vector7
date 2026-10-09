<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notify;
use App\Support\Excel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** All marketplace enquiries. Assign to a tenant or the App team; status new → contacted → closed. */
class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $q = Enquiry::with(['tenantRel:id,name', 'project:id,name', 'plot:id,plot_no', 'assignee:id,name'])->latest('id')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->when($request->query('tenant'), fn ($q, $t) => $t === 'app' ? $q->where('assigned_team', 'app') : $q->where('tenant_id', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s));
        if ($request->query('export') === 'xlsx') {
            return Excel::download('enquiries.xlsx', ['Date', 'Name', 'Email', 'Mobile', 'Tenant', 'Project', 'Plot', 'Message', 'Team', 'Assignee', 'Status'],
                $q->get()->map(fn ($e) => [$e->created_at, $e->name, $e->email, $e->mobile, $e->tenantRel?->name, $e->project?->name, $e->plot?->plot_no, $e->message, $e->assigned_team, $e->assignee?->name, Enquiry::STATUSES[$e->status] ?? $e->status]), 'Enquiries');
        }

        return view('app.enquiries.index', [
            'enquiries' => $q->paginate(25)->withQueryString(),
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id'),
            'appUsers' => User::whereNull('tenant_id')->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'counts' => Enquiry::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'assigned_team' => 'required|in:app,tenant',
            'tenant_id' => ['nullable', 'required_if:assigned_team,tenant', 'integer', Rule::exists('tenants', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('tenant_id')],
            'status' => ['required', Rule::in(array_keys(Enquiry::STATUSES))],
            'follow_up_on' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        if ($data['assigned_team'] === 'tenant') {
            $data['assigned_to'] = (int) $data['tenant_id'] === (int) $enquiry->tenant_id ? $enquiry->assigned_to : null;
        } else {
            $data['tenant_id'] = $enquiry->tenant_id;
        }
        $handedToTenant = $data['assigned_team'] === 'tenant' && (int) $data['tenant_id'] !== (int) $enquiry->tenant_id;
        $enquiry->update($data);
        if ($handedToTenant && ($tenant = Tenant::find($data['tenant_id']))) {
            Notify::groups($tenant->id, ['Sales Team'], 'enquiry_received', [
                'name' => $enquiry->name, 'email' => $enquiry->email, 'mobile' => (string) $enquiry->mobile,
                'project' => $enquiry->project?->name ?? 'your projects', 'message' => $enquiry->message, 'link' => route('ws.enquiries.index'),
            ]);
        }

        return back()->with('ok', 'Enquiry updated.');
    }
}
