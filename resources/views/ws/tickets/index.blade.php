<x-layouts.workspace title="Help desk">
    <x-page-header title="Help desk" subtitle="Ask the vector7 team for help. Replies arrive here and by email.">
        <a href="{{ route('ws.services.index') }}" class="btn-light"><x-icon name="briefcase" class="h-4 w-4" /> Request a service</a>
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card lg:col-span-3">
            <form method="GET" class="flex gap-2 p-4" role="search">
                <x-select name="status" :options="\App\Models\Ticket::STATUSES" :value="request('status')" placeholder="Any status" aria-label="Status" />
                <button class="btn-light">Filter</button>
            </form>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Ticket</th><th>Subject</th><th>Raised by</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                @forelse ($tickets as $t)
                    <tr>
                        <td><a href="{{ route('ws.tickets.show', $t) }}" class="font-mono text-xs font-semibold">{{ $t->number }}</a></td>
                        <td>{{ $t->subject }}</td>
                        <td class="text-sm">{{ $t->requester_name }}</td>
                        <td><span class="badge-navy">{{ \App\Models\Ticket::STATUSES[$t->status] }}</span></td>
                        <td class="text-xs">@date($t->updated_at)</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty title="No tickets" icon="lifebuoy" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="p-4">{{ $tickets->links() }}</div>
        </div>
        @can('tickets.create')
            <form method="POST" action="{{ route('ws.tickets.store') }}" enctype="multipart/form-data" class="card card-pad h-fit space-y-3 lg:col-span-2">
                @csrf
                <h2 class="section-title">New ticket</h2>
                <x-select name="category" label="Topic" :options="\App\Models\Ticket::CATEGORIES" />
                <x-select name="priority" label="Priority" :options="\App\Models\Ticket::PRIORITIES" value="medium" />
                <x-field name="subject" label="Subject" required />
                <x-textarea name="body" label="Details" rows="5" required />
                <x-field name="file" type="file" label="Attachment (optional)" accept=".pdf,.jpg,.jpeg,.png,.webp" />
                <button class="btn-primary">Open ticket</button>
            </form>
        @endcan
    </div>
</x-layouts.workspace>
