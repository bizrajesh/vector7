<x-layouts.workspace title="Help desk">
    <x-page-header title="Help desk" subtitle="Tickets from promoters and buyers. SLA: urgent 8 h · high 24 h · medium 48 h · low 72 h.">
        <x-export-buttons />
    </x-page-header>
    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Open" :value="$summary['open']" icon="lifebuoy" />
        <x-stat label="Past SLA" :value="$summary['overdue']" icon="clock" tone="red" />
        <x-stat label="Unassigned" :value="$summary['unassigned']" icon="user" tone="gold" />
        <x-stat label="Resolved (7 days)" :value="$summary['resolved_week']" icon="check" tone="navy" />
    </div>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Ticket no., subject, email" />
        <x-select name="status" label="Status" :options="['open_all' => 'All unresolved'] + \App\Models\Ticket::STATUSES" :value="request('status')" placeholder="Any" />
        <x-select name="priority" label="Priority" :options="\App\Models\Ticket::PRIORITIES" :value="request('priority')" placeholder="Any" />
        <x-select name="category" label="Category" :options="\App\Models\Ticket::CATEGORIES" :value="request('category')" placeholder="Any" />
        <x-select name="from" label="From" :options="['tenant' => 'Promoters', 'customer' => 'Buyers']" :value="request('from')" placeholder="Everyone" />
        <x-select name="assignee" label="Assignee" :options="['me' => 'Me', 'none' => 'Unassigned'] + $agents->all()" :value="request('assignee')" placeholder="Anyone" />
    </x-filters>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Ticket</th><th>Subject</th><th>From</th><th>Priority</th><th>Status</th><th>Assignee</th><th>SLA due</th></tr></thead>
        <tbody>
        @forelse ($tickets as $t)
            <tr>
                <td><a href="{{ route('app.tickets.show', $t) }}" class="font-mono text-xs font-semibold">{{ $t->number }}</a><p class="text-xs text-muted">{{ \App\Models\Ticket::CATEGORIES[$t->category] ?? $t->category }}</p></td>
                <td class="max-w-xs"><p class="truncate">{{ $t->subject }}</p></td>
                <td>{{ $t->requester_name }}<p class="text-xs text-muted">{{ $t->tenant?->name ?? 'Buyer' }}</p></td>
                <td><span class="{{ ['urgent' => 'badge-red', 'high' => 'badge-amber', 'medium' => 'badge-blue', 'low' => 'badge-gray'][$t->priority] ?? 'badge-gray' }}">{{ \App\Models\Ticket::PRIORITIES[$t->priority] ?? $t->priority }}</span></td>
                <td><span class="badge-navy">{{ \App\Models\Ticket::STATUSES[$t->status] ?? $t->status }}</span></td>
                <td>{{ $t->assignee?->name ?? '—' }}</td>
                <td class="text-xs {{ $t->isOverdue() ? 'font-bold text-red-700' : '' }}">{{ \App\Support\Format::datetime($t->sla_due_at) }}@if ($t->isOverdue())<span class="block">Past SLA</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty title="No tickets" icon="lifebuoy" /></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $tickets->links() }}</div></div>
</x-layouts.workspace>
