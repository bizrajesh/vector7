<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\Project;
use App\Models\User;
use App\Support\Excel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Marketplace enquiries for this tenant's projects: assign, follow-up date, status, convert to booking. */
class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $q = Enquiry::with(['project:id,name', 'plot:id,plot_no,project_id', 'assignee:id,name', 'customer:id'])->latest('id')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%")))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('assignee') === 'me', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->boolean('due'), fn ($q) => $q->whereIn('status', ['new', 'contacted'])->whereDate('follow_up_on', '<=', today()));
        if ($request->query('export') === 'xlsx') {
            return Excel::download('enquiries.xlsx', ['Date', 'Name', 'Email', 'Mobile', 'Project', 'Plot', 'Message', 'Assignee', 'Follow-up', 'Status'],
                $q->get()->map(fn ($e) => [$e->created_at, $e->name, $e->email, $e->mobile, $e->project?->name, $e->plot?->plot_no, $e->message, $e->assignee?->name, $e->follow_up_on, Enquiry::STATUSES[$e->status] ?? $e->status]), 'Enquiries');
        }

        return view('ws.enquiries.index', [
            'enquiries' => $q->paginate(20)->withQueryString(),
            'projects' => Project::orderBy('name')->pluck('name', 'id'),
            'staff' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'dueToday' => Enquiry::whereIn('status', ['new', 'contacted'])->whereDate('follow_up_on', '<=', today())->count(),
        ]);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $enquiry->tenant_id)],
            'status' => ['required', Rule::in(array_keys(Enquiry::STATUSES))],
            'follow_up_on' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'booking_no' => 'nullable|required_if:status,converted|string|max:40',
        ], ['booking_no.required_if' => 'Enter the booking number this enquiry converted into.']);
        if ($data['status'] === 'converted') {
            $booking = Booking::where('booking_no', $data['booking_no'])->first();
            if (! $booking) {
                return back()->withInput()->with('error', "Booking {$data['booking_no']} was not found in your workspace.");
            }
            $data['booking_id'] = $booking->id;
        }
        unset($data['booking_no']);
        $enquiry->update($data);

        return back()->with('ok', 'Enquiry updated.');
    }
}
