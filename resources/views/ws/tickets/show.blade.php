<x-layouts.workspace :title="$ticket->number">
    <x-page-header :title="$ticket->subject" :subtitle="$ticket->number.' · '.(\App\Models\Ticket::CATEGORIES[$ticket->category] ?? $ticket->category).' · opened by '.$ticket->requester_name" :back="route('ws.tickets.index')">
        <span class="badge-navy">{{ \App\Models\Ticket::STATUSES[$ticket->status] }}</span>
    </x-page-header>
    <div class="card card-pad">
        <ol class="space-y-4">
            @foreach ($ticket->messages as $m)
                @php($fromUs = $m->author_type === 'user' && \App\Models\User::whereKey($m->author_id)->where('tenant_id', $ticket->tenant_id)->exists())
                <li class="rounded-xl p-4 {{ $fromUs ? 'bg-page' : 'bg-teal-50' }}">
                    <p class="text-xs text-muted"><strong class="text-navy">{{ $m->author_name }}{{ $fromUs ? '' : ' (vector7 support)' }}</strong> · {{ \App\Support\Format::datetime($m->created_at) }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $m->body }}</p>
                    @if ($m->attachment)<a href="{{ route('files.show', $m->attachment) }}" class="mt-2 inline-flex items-center gap-1 text-sm" target="_blank" rel="noopener"><x-icon name="doc" class="h-4 w-4" /> {{ $m->attachment->original_name }}</a>@endif
                </li>
            @endforeach
        </ol>
        @if ($ticket->status !== 'closed')
            @can('tickets.create')
                <form method="POST" action="{{ route('ws.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="mt-6 space-y-3 border-t border-navy-50 pt-4">
                    @csrf
                    <x-textarea name="body" label="Reply" rows="4" required />
                    <x-field name="file" type="file" label="Attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" />
                    <button class="btn-primary">Send reply</button>
                </form>
            @endcan
        @endif
    </div>
</x-layouts.workspace>
