<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\FileStore;
use App\Services\TicketService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    private function mine(Request $request)
    {
        return Ticket::withoutGlobalScopes()->where('requester_type', 'customer')->where('requester_id', $request->user('customer')->id);
    }

    public function index(Request $request)
    {
        return view('account.tickets', ['tickets' => $this->mine($request)->latest('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:5000',
            'file' => FileStore::rules('document', 5120, false),
        ]);
        $t = TicketService::open($request->user('customer'), $data + ['priority' => 'medium'], $request->file('file'));

        return redirect()->route('account.tickets.show', $t->id)->with('ok', "Ticket {$t->number} opened. We'll reply by email.");
    }

    public function show(Request $request, int $ticket)
    {
        $t = $this->mine($request)->findOrFail($ticket);

        return view('account.ticket', ['ticket' => $t->load(['messages' => fn ($q) => $q->where('is_internal', false)->with('attachment')])]);
    }

    public function reply(Request $request, int $ticket)
    {
        $t = $this->mine($request)->findOrFail($ticket);
        $data = $request->validate(['body' => 'required|string|max:5000', 'file' => FileStore::rules('document', 5120, false)]);
        app(Tenancy::class)->withoutScope(fn () => TicketService::addMessage($t, $request->user('customer'), $data['body'], $request->file('file')));

        return back()->with('ok', 'Reply sent.');
    }

    public function requestService(Request $request, Service $service)
    {
        abort_unless($service->is_active && in_array($service->available_to, ['customer', 'both'], true), 404);
        $data = $request->validate(['notes' => 'required|string|max:3000']);
        $sr = TicketService::requestService($service, $request->user('customer'), $data['notes'], null);

        return redirect()->route('account.tickets.show', $sr->ticket_id)->with('ok', 'Service requested. Our team will contact you.');
    }
}
