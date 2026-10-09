<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Plot;
use App\Models\Project;
use App\Services\Notify;
use Illuminate\Http\Request;

/** Project / plot enquiry from the marketplace → the tenant's Enquiries (and the Sales Team group). */
class EnquiryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|integer',
            'plot_id' => 'nullable|integer',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'message' => 'required|string|max:2000',
            'website' => 'nullable|max:0', // honeypot
        ], ['mobile.regex' => 'Enter a 10-digit mobile number.']);
        $project = Project::launched()->findOrFail($data['project_id']);
        $plot = isset($data['plot_id']) ? Plot::where('project_id', $project->id)->find($data['plot_id']) : null;
        $customer = auth('customer')->user();
        $e = Enquiry::create([
            'tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'plot_id' => $plot?->id, 'customer_id' => $customer?->id,
            'name' => $data['name'], 'email' => strtolower($data['email']), 'mobile' => $data['mobile'], 'message' => $data['message'],
            'source' => 'marketplace', 'assigned_team' => 'tenant',
        ]);
        if ($customer) {
            $customer->associateWith($project->tenant_id, 'enquiry');
        }
        Notify::groups($project->tenant_id, ['Sales Team'], 'enquiry_received', [
            'name' => $e->name, 'email' => $e->email, 'mobile' => $e->mobile, 'project' => $project->name.($plot ? ' – Plot '.$plot->plot_no : ''),
            'message' => $e->message, 'link' => route('ws.enquiries.index'),
        ]);

        return back()->with('ok', 'Thanks! '.$project->tenantRel->name.' will contact you shortly.');
    }
}
