<x-layouts.account title="Help & tickets">
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card lg:col-span-3"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>@forelse ($tickets as $t)<tr><td class="font-mono text-xs"><a href="{{ route('account.tickets.show', $t->id) }}">{{ $t->number }}</a></td><td>{{ $t->subject }}</td><td><span class="badge-navy">{{ \App\Models\Ticket::STATUSES[$t->status] }}</span></td><td class="text-xs">@date($t->updated_at)</td></tr>@empty<tr><td colspan="4"><x-empty title="No tickets" icon="lifebuoy" /></td></tr>@endforelse</tbody>
        </table></div></div>
        <form method="POST" action="{{ route('account.tickets.store') }}" enctype="multipart/form-data" class="card card-pad space-y-3 lg:col-span-2">
            @csrf
            <h2 class="section-title">New ticket</h2>
            <x-select name="category" label="Topic" :options="collect(\App\Models\Ticket::CATEGORIES)->except(['billing'])->all()" />
            <x-field name="subject" label="Subject" required />
            <x-textarea name="body" label="Details" rows="5" required />
            <x-field name="file" type="file" label="Attachment (optional)" accept=".pdf,.jpg,.jpeg,.png,.webp" />
            <button class="btn-primary">Open ticket</button>
        </form>
    </div>
</x-layouts.account>
