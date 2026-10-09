<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Help desk: tickets from tenants and customers, SLA by priority, threaded replies, attachments, email updates. */
class TicketService
{
    public static function open(User|Customer $requester, array $data, ?UploadedFile $file = null, ?int $tenantId = null): Ticket
    {
        return DB::transaction(function () use ($requester, $data, $file, $tenantId) {
            $priority = $data['priority'] ?? 'medium';
            $ticket = Ticket::create([
                'number' => self::number(),
                'tenant_id' => $tenantId,
                'requester_type' => $requester instanceof Customer ? 'customer' : 'user',
                'requester_id' => $requester->id,
                'requester_name' => $requester->name,
                'requester_email' => $requester->email,
                'category' => $data['category'] ?? 'general',
                'priority' => $priority,
                'subject' => $data['subject'],
                'status' => 'open',
                'sla_due_at' => now()->addHours(Ticket::SLA_HOURS[$priority] ?? 48),
            ]);
            self::addMessage($ticket, $requester, $data['body'], $file, false, notify: false);
            Notify::send('ticket_update', [(string) AppSettings::get('org.support_email')], [
                'name' => 'vector7 support', 'ticket_no' => $ticket->number, 'subject' => $ticket->subject, 'status' => 'Open',
                'message' => $data['body'], 'link' => route('app.tickets.show', $ticket),
            ]);

            return $ticket;
        });
    }

    public static function addMessage(Ticket $ticket, User|Customer $author, string $body, ?UploadedFile $file = null, bool $internal = false, bool $notify = true): TicketMessage
    {
        $m = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_type' => $author instanceof Customer ? 'customer' : 'user',
            'author_id' => $author->id,
            'author_name' => $author->name,
            'body' => $body,
            'is_internal' => $internal,
        ]);
        if ($file) {
            $m->update(['attachment_file_id' => FileStore::store($file, 'ticket', $ticket->tenant_id, $m)->id]);
        }
        $fromRequester = ($author instanceof Customer && $ticket->requester_type === 'customer') || ($author instanceof User && $ticket->requester_type === 'user' && $author->id === $ticket->requester_id);
        if (! $fromRequester && ! $internal && in_array($ticket->status, ['open', 'waiting'], true)) {
            $ticket->update(['status' => 'in_progress']);
        }
        if ($fromRequester && $ticket->status === 'waiting') {
            $ticket->update(['status' => 'open']);
        }
        if ($notify && ! $internal) {
            $toRequester = ! $fromRequester;
            $link = $ticket->requester_type === 'customer' ? route('account.tickets.show', $ticket) : ($ticket->tenant_id ? route('ws.tickets.show', $ticket) : route('app.tickets.show', $ticket));
            Notify::send('ticket_update', [$toRequester ? $ticket->requester_email : (string) AppSettings::get('org.support_email')], [
                'name' => $toRequester ? $ticket->requester_name : 'vector7 support', 'ticket_no' => $ticket->number, 'subject' => $ticket->subject,
                'status' => Ticket::STATUSES[$ticket->status] ?? $ticket->status, 'message' => $body,
                'link' => $toRequester ? $link : route('app.tickets.show', $ticket),
            ], $ticket->tenant_id);
        }

        return $m;
    }

    public static function requestService(Service $service, User|Customer $requester, string $notes, ?int $tenantId): ServiceRequest
    {
        $ticket = self::open($requester, ['category' => 'service', 'priority' => 'medium', 'subject' => 'Service request: '.$service->name, 'body' => $notes], null, $tenantId);

        return ServiceRequest::create([
            'service_id' => $service->id, 'tenant_id' => $tenantId, 'customer_id' => $requester instanceof Customer ? $requester->id : null,
            'user_id' => $requester instanceof User ? $requester->id : null, 'notes' => $notes, 'status' => 'new', 'ticket_id' => $ticket->id,
        ]);
    }

    private static function number(): string
    {
        do {
            $n = 'TKT-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Ticket::withoutGlobalScopes()->where('number', $n)->exists());

        return $n;
    }
}
