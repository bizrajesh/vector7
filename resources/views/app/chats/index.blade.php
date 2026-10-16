<x-layouts.workspace title="Chatbot conversations">
    <x-page-header title="Chatbot conversations" subtitle="Marketplace and workspace assistant conversations, kept for 90 days.">
        <x-export-buttons />
    </x-page-header>
    <x-filters>
        <x-select name="scope" label="Bot" :options="['market' => 'Marketplace', 'workspace' => 'Workspace']" :value="request('scope')" placeholder="Both" />
        <x-select name="tenant" label="Workspace" :options="$tenants" :value="request('tenant')" placeholder="All" />
        <x-field name="q" label="Contains" :value="request('q')" />
        <x-field name="from" type="date" label="From" :value="request('from')" />
        <x-field name="to" type="date" label="To" :value="request('to')" />
    </x-filters>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Last message</th><th>Bot</th><th>Who</th><th>Topic</th><th class="num">Messages</th></tr></thead>
        <tbody>
        @forelse ($conversations as $c)
            <tr>
                <td class="text-xs"><a href="{{ route('app.chats.show', $c) }}">{{ \App\Support\Format::datetime($c->last_message_at) }}</a></td>
                <td><span class="{{ $c->scope === 'market' ? 'badge-teal' : 'badge-navy' }}">{{ $c->scope === 'market' ? 'Marketplace' : 'Workspace' }}</span></td>
                <td class="text-sm">{{ $c->customer?->name ?? $c->user?->name ?? 'Visitor' }}<p class="text-xs text-muted">{{ $c->tenant?->name }}</p></td>
                <td class="max-w-sm text-sm"><p class="truncate">{{ $c->title }}</p></td>
                <td class="num">{{ $c->message_count }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="No conversations" icon="chat" /></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $conversations->links() }}</div></div>
</x-layouts.workspace>
