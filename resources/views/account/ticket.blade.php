<x-layouts.account :title="$ticket->number">
    <div class="card card-pad">
        <div class="flex flex-wrap items-start justify-between gap-2"><h2 class="text-xl font-extrabold">{{ $ticket->subject }}</h2><span class="badge-navy">{{ \App\Models\Ticket::STATUSES[$ticket->status] }}</span></div>
        <ol class="mt-6 space-y-4">
            @foreach ($ticket->messages as $m)
                <li class="rounded-xl p-4 {{ $m->author_type === 'customer' ? 'bg-page' : 'bg-teal-50' }}">
                    <p class="text-xs text-muted"><strong class="text-navy">{{ $m->author_type === 'customer' ? 'You' : $m->author_name.' (support)' }}</strong> · {{ \App\Support\Format::datetime($m->created_at) }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $m->body }}</p>
                    @if ($m->attachment)<a href="{{ route('account.file', $m->attachment) }}" class="mt-2 inline-block text-sm" target="_blank" rel="noopener">{{ $m->attachment->original_name }}</a>@endif
                </li>
            @endforeach
        </ol>
        @unless (in_array($ticket->status, ['closed']))
            <form method="POST" action="{{ route('account.tickets.reply', $ticket->id) }}" enctype="multipart/form-data" class="mt-6 space-y-3">@csrf
                <x-textarea name="body" label="Reply" rows="4" required />
                <x-field name="file" type="file" label="Attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" />
                <button class="btn-primary">Send reply</button>
            </form>
        @endunless
    </div>
</x-layouts.account>
