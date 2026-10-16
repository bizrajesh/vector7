<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Project;
use App\Services\LeadService;
use Illuminate\Http\Request;

/** Project / plot enquiry from the marketplace → a lead in the promoter's Leads (source "Enquiry form"). */
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
        LeadService::capture($project->tenant_id, 'enquiry', [
            'name' => $data['name'], 'email' => $data['email'], 'mobile' => $data['mobile'], 'customer_id' => $customer?->id,
            'message' => $data['message'], 'consent' => true,
        ], array_filter(['project_id' => $project->id, 'plot_id' => $plot?->id]));

        return back()->with('ok', 'Thanks! '.$project->tenantRel->name.' will contact you shortly.');
    }
}
