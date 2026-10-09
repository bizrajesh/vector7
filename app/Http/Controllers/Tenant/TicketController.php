<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Services\FileStore;
use App\Services\TicketService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tenant help desk: tickets this workspace raised with the vector7 team, and requests for App services. */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        return view('ws.tickets.index', [
            'tickets' => Ticket::where('requester_type', 'user')
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'file' => FileStore::rules('document', 5120, false),
        ]);
        $t = TicketService::open($request->user(), $data, $request->file('file'), app(Tenancy::class)->id());

        return redirect()->route('ws.tickets.show', $t)->with('ok', "Ticket {$t->number} opened. The vector7 team will reply here and by email.");
    }

    public function show(Ticket $ticket)
    {
        abort_unless($ticket->requester_type === 'user', 404);

        return view('ws.tickets.show', ['ticket' => $ticket->load(['messages' => fn ($q) => $q->where('is_internal', false)->with('attachment')])]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->requester_type === 'user' && $ticket->status !== 'closed', 404);
        $data = $request->validate(['body' => 'required|string|max:5000', 'file' => FileStore::rules('document', 5120, false)]);
        TicketService::addMessage($ticket, $request->user(), $data['body'], $request->file('file'));

        return back()->with('ok', 'Reply sent.');
    }

    public function services()
    {
        return view('ws.tickets.services', [
            'services' => Service::where('is_active', true)->whereIn('available_to', ['tenant', 'both'])->orderBy('sort')->orderBy('name')->get(),
            'requests' => ServiceRequest::with(['service', 'ticket:id,number,status'])->latest('id')->take(20)->get(),
        ]);
    }

    public function requestService(Request $request, Service $service)
    {
        abort_unless($service->is_active && in_array($service->available_to, ['tenant', 'both'], true), 404);
        $data = $request->validate(['notes' => 'required|string|max:3000']);
        $sr = TicketService::requestService($service, $request->user(), $data['notes'], app(Tenancy::class)->id());

        return redirect()->route('ws.tickets.show', $sr->ticket_id)->with('ok', 'Service requested. The vector7 team will contact you.');
    }
}
