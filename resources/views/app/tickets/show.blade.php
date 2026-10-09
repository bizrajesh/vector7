<x-layouts.workspace :title="$ticket->number">
    <x-page-header :title="$ticket->subject" :subtitle="$ticket->number.' · '.$ticket->requester_name.' <'.$ticket->requester_email.'>'.($tenantName ? ' · '.$tenantName : ' · Buyer')" :back="route('app.tickets.index')" />
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card card-pad xl:col-span-2">
            <ol class="space-y-4">
                @foreach ($ticket->messages as $m)
                    <li class="rounded-xl p-4 {{ $m->is_internal ? 'bg-amber-50 ring-1 ring-amber-200' : ($m->author_type === 'user' && $m->author_id !== $ticket->requester_id ? 'bg-teal-50' : 'bg-page') }}">
                        <p class="text-xs text-muted"><strong class="text-navy">{{ $m->author_name }}</strong>@if ($m->is_internal) · <span class="font-semibold text-amber-800">Internal note</span>@endif · {{ \App\Support\Format::datetime($m->created_at) }}</p>
                        <p class="mt-1 whitespace-pre-line">{{ $m->body }}</p>
                        @if ($m->attachment)<a href="{{ route('files.show', $m->attachment) }}" class="mt-2 inline-flex items-center gap-1 text-sm" target="_blank" rel="noopener"><x-icon name="doc" class="h-4 w-4" /> {{ $m->attachment->original_name }}</a>@endif
                    </li>
                @endforeach
            </ol>
            @can('helpdesk.update')
                <form method="POST" action="{{ route('app.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="mt-6 space-y-3 border-t border-navy-50 pt-4">
                    @csrf
                    <x-textarea name="body" label="Reply" rows="5" required />
                    <x-field name="file" type="file" label="Attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" />
                    <div class="flex flex-wrap items-center gap-4">
                        <input type="hidden" name="is_internal" value="0"><x-checkbox name="is_internal" label="Internal note (not sent to the requester)" />
                        <x-select name="set_status" label="Then set status" :options="['waiting' => 'Waiting on requester', 'resolved' => 'Resolved']" placeholder="Keep as is" />
                    </div>
                    <button class="btn-primary"><x-icon name="mail" class="h-4 w-4" /> Send</button>
                </form>
            @endcan
        </div>
        <div class="space-y-6">
            <form method="POST" action="{{ route('app.tickets.update', $ticket) }}" class="card card-pad space-y-3">
                @csrf @method('PUT')
                <h2 class="section-title">Ticket</h2>
                <x-select name="status" label="Status" :options="\App\Models\Ticket::STATUSES" :value="$ticket->status" />
                <x-select name="priority" label="Priority" :options="\App\Models\Ticket::PRIORITIES" :value="$ticket->priority" hint="Changing priority recalculates the SLA." />
                <x-select name="category" label="Category" :options="\App\Models\Ticket::CATEGORIES" :value="$ticket->category" />
                <x-select name="assignee_id" label="Assignee" :options="$agents" :value="$ticket->assignee_id" placeholder="Unassigned" />
                @can('helpdesk.update')<button class="btn-primary">Update ticket</button>@endcan
            </form>
            <div class="card card-pad text-sm">
                <dl class="grid grid-cols-2 gap-y-2">
                    <dt class="text-muted">Opened</dt><dd>{{ \App\Support\Format::datetime($ticket->created_at) }}</dd>
                    <dt class="text-muted">SLA due</dt><dd class="{{ $ticket->isOverdue() ? 'font-bold text-red-700' : '' }}">{{ \App\Support\Format::datetime($ticket->sla_due_at) }}</dd>
                    <dt class="text-muted">Resolved</dt><dd>{{ $ticket->resolved_at ? \App\Support\Format::datetime($ticket->resolved_at) : '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</x-layouts.workspace>
