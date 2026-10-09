<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\FileStore;
use App\Services\Notify;
use App\Services\TicketService;
use App\Support\Excel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Help desk: tickets from tenants and customers — category, priority, SLA, assignee, status, threaded replies, attachments. */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $q = Ticket::with(['assignee:id,name', 'tenant:id,name'])->latest('id')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('subject', 'like', "%$s%")->orWhere('requester_email', 'like', "%$s%")))
            ->when($request->query('status'), fn ($q, $s) => $s === 'open_all' ? $q->whereNotIn('status', ['resolved', 'closed']) : $q->where('status', $s))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('from'), fn ($q, $f) => $f === 'customer' ? $q->where('requester_type', 'customer') : $q->where('requester_type', 'user'))
            ->when($request->query('assignee'), fn ($q, $a) => $a === 'me' ? $q->where('assignee_id', $request->user()->id) : ($a === 'none' ? $q->whereNull('assignee_id') : $q->where('assignee_id', $a)))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereNotIn('status', ['resolved', 'closed'])->where('sla_due_at', '<', now()));
        if ($request->query('export') === 'xlsx') {
            return Excel::download('tickets.xlsx', ['Ticket', 'Opened', 'From', 'Tenant', 'Category', 'Priority', 'Subject', 'Status', 'Assignee', 'SLA due', 'Resolved'],
                $q->get()->map(fn ($t) => [$t->number, $t->created_at, $t->requester_name.' <'.$t->requester_email.'>', $t->tenant?->name, Ticket::CATEGORIES[$t->category] ?? $t->category, Ticket::PRIORITIES[$t->priority] ?? $t->priority, $t->subject, Ticket::STATUSES[$t->status] ?? $t->status, $t->assignee?->name, $t->sla_due_at, $t->resolved_at]), 'Tickets');
        }

        return view('app.tickets.index', [
            'tickets' => $q->paginate(25)->withQueryString(),
            'agents' => $this->agents(),
            'summary' => [
                'open' => Ticket::whereNotIn('status', ['resolved', 'closed'])->count(),
                'overdue' => Ticket::whereNotIn('status', ['resolved', 'closed'])->where('sla_due_at', '<', now())->count(),
                'unassigned' => Ticket::whereNotIn('status', ['resolved', 'closed'])->whereNull('assignee_id')->count(),
                'resolved_week' => Ticket::where('resolved_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }

    public function show(Ticket $ticket)
    {
        return view('app.tickets.show', [
            'ticket' => $ticket->load(['messages.attachment', 'assignee', 'tenant']),
            'agents' => $this->agents(),
            'tenantName' => $ticket->tenant_id ? Tenant::find($ticket->tenant_id)?->name : null,
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('tenant_id')],
        ]);
        if ($data['priority'] !== $ticket->priority) {
            $data['sla_due_at'] = $ticket->created_at->copy()->addHours(Ticket::SLA_HOURS[$data['priority']]);
        }
        $statusChanged = $data['status'] !== $ticket->status;
        if (in_array($data['status'], ['resolved', 'closed'], true) && ! $ticket->resolved_at) {
            $data['resolved_at'] = now();
        } elseif (! in_array($data['status'], ['resolved', 'closed'], true)) {
            $data['resolved_at'] = null;
        }
        $ticket->update($data);
        if ($statusChanged) {
            Notify::send('ticket_update', [$ticket->requester_email], [
                'name' => $ticket->requester_name, 'ticket_no' => $ticket->number, 'subject' => $ticket->subject,
                'status' => Ticket::STATUSES[$ticket->status], 'message' => 'The status of your ticket was updated.',
                'link' => $ticket->requester_type === 'customer' ? route('account.tickets.show', $ticket->id) : route('ws.tickets.show', $ticket->id),
            ], $ticket->tenant_id);
        }

        return back()->with('ok', 'Ticket updated.');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'is_internal' => 'boolean',
            'file' => FileStore::rules('document', 5120, false),
            'set_status' => ['nullable', Rule::in(['waiting', 'resolved'])],
        ]);
        TicketService::addMessage($ticket, $request->user(), $data['body'], $request->file('file'), $request->boolean('is_internal'));
        if (! empty($data['set_status'])) {
            $ticket->update(['status' => $data['set_status'], 'resolved_at' => $data['set_status'] === 'resolved' ? now() : null]);
        }
        if (! $ticket->assignee_id) {
            $ticket->update(['assignee_id' => $request->user()->id]);
        }

        return back()->with('ok', $request->boolean('is_internal') ? 'Internal note added.' : 'Reply sent to '.$ticket->requester_email.'.');
    }

    private function agents()
    {
        return User::whereNull('tenant_id')->where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }
}
