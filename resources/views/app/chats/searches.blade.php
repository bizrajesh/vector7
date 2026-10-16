<x-layouts.workspace title="AI search log">
    <x-page-header title="AI search log" subtitle="What buyers searched for in plain words, the filters used, what was relaxed and how many results came back. Kept for 90 days." :back="route('app.chats.index')">
        <x-export-buttons />
        <a href="{{ route('help.show', 'app-chats') }}" class="btn-ghost" data-help><x-icon name="info" class="h-4 w-4" /> Help</a>
    </x-page-header>
    <x-filters>
        <x-field name="q" label="Search text" :value="request('q')" />
        <x-field name="from" type="date" label="From" :value="request('from')" />
        <x-field name="to" type="date" label="To" :value="request('to')" />
        <x-select name="zero" label="Results" :options="['1' => 'No results only']" :value="request('zero')" placeholder="All" />
    </x-filters>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Time</th><th>Search</th><th>Understood as</th><th>Relaxed</th><th class="num">Results</th><th>AI</th></tr></thead>
        <tbody>
        @forelse ($logs as $l)
            <tr>
                <td class="whitespace-nowrap text-xs">{{ \App\Support\Format::datetime($l->created_at) }}<p class="text-muted">{{ $l->customer?->name ?? 'Visitor' }}</p></td>
                <td class="max-w-xs text-sm">{{ $l->query }}</td>
                <td class="max-w-sm text-xs">{{ implode(' · ', array_column(\App\Services\PropertySearch::chips(\App\Services\PropertySearch::clean($l->filters ?? [])), 0)) ?: '—' }}</td>
                <td class="max-w-xs text-xs">{{ implode('; ', (array) $l->relaxed) ?: '—' }}</td>
                <td class="num {{ $l->result_count ? '' : 'text-red-700 font-semibold' }}">{{ $l->result_count }}</td>
                <td class="text-xs">{{ $l->used_ai ? ($l->cached ? 'Cached' : 'Yes') : 'Keyword' }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty title="No searches yet" icon="search" /></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $logs->links() }}</div></div>
</x-layouts.workspace>
