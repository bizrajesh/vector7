<x-layouts.workspace title="Leads">
    <x-page-header title="Leads" subtitle="Everyone who called, messaged, enquired, chatted or booked — one pipeline. Drag cards between stages or use “Move to”.">
        <a href="{{ route('ws.leads.reports') }}" class="btn-light"><x-icon name="chart" class="h-4 w-4" /> Reports</a>
        @can('leads.export')<x-export-buttons />@endcan
        <a href="{{ route('help.show', 'leads') }}" class="btn-ghost" data-help><x-icon name="info" class="h-4 w-4" /> Help</a>
    </x-page-header>

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <a href="{{ route('ws.leads.index', ['view' => 'list', 'stage' => $stages->firstWhere('key', 'new')?->id]) }}" class="no-underline"><x-stat label="New, not contacted" :value="$counts['new']" icon="bell" tone="gold" /></a>
        <a href="{{ route('ws.leads.index', ['view' => 'list', 'due' => 'today']) }}" class="no-underline"><x-stat label="Follow-ups due today" :value="$counts['due']" icon="calendar" /></a>
        <a href="{{ route('ws.leads.index', ['view' => 'list', 'due' => 'overdue']) }}" class="no-underline"><x-stat label="Overdue follow-ups" :value="$counts['overdue']" icon="clock" tone="red" /></a>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <nav class="tabs border-0" aria-label="View">
            <a href="{{ route('ws.leads.index', array_merge(request()->except('view', 'page'), ['view' => 'board'])) }}" class="tab {{ $view === 'board' ? 'active' : '' }}" @if ($view === 'board') aria-current="page" @endif>Board</a>
            <a href="{{ route('ws.leads.index', array_merge(request()->except('view', 'page'), ['view' => 'list'])) }}" class="tab {{ $view === 'list' ? 'active' : '' }}" @if ($view === 'list') aria-current="page" @endif>List</a>
        </nav>
        @can('leads.create')
            <details class="relative" data-dropdown>
                <summary class="btn-primary list-none cursor-pointer"><x-icon name="plus" class="h-4 w-4" /> Add lead</summary>
                <form method="POST" action="{{ route('ws.leads.store') }}" class="absolute right-0 z-30 mt-2 grid w-[min(92vw,26rem)] gap-3 rounded-xl bg-white p-4 shadow-lift ring-1 ring-navy-50">
                    @csrf
                    <x-field name="name" label="Name" required id="nl_name" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="mobile" label="Mobile" id="nl_mobile" inputmode="numeric" maxlength="10" />
                        <x-field name="email" type="email" label="Email" id="nl_email" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-select name="source" label="Source" :options="collect(\App\Models\Lead::SOURCES)->only(\App\Models\Lead::MANUAL_SOURCES)->all()" id="nl_source" />
                        <x-select name="priority" label="Priority" :options="\App\Models\Lead::PRIORITIES" value="warm" id="nl_priority" />
                    </div>
                    <x-select name="interest" label="Interested in" :options="$interestOptions" placeholder="—" id="nl_interest" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="budget" type="number" label="Budget (₹)" id="nl_budget" min="0" />
                        <x-field name="preferred_location" label="Preferred location" id="nl_loc" />
                    </div>
                    <x-textarea name="notes" label="Requirement notes" rows="2" id="nl_notes" />
                    <x-checkbox name="consent" label="The person agreed to be contacted" id="nl_consent" />
                    <button class="btn-primary">Add lead</button>
                </form>
            </details>
        @endcan
    </div>

    <x-filters>
        <input type="hidden" name="view" value="{{ $view }}">
        <x-field name="q" label="Search" :value="request('q')" placeholder="Name, mobile, email, lead ID" />
        @if ($view === 'list')<x-select name="stage" label="Stage" :options="$stages->pluck('name', 'id')" :value="request('stage')" placeholder="All" />@endif
        <x-select name="source" label="Source" :options="\App\Models\Lead::SOURCES" :value="request('source')" placeholder="All" />
        <x-select name="listing" label="Listing / layout" :options="$interestOptions" :value="request('listing')" placeholder="All" />
        <x-select name="assigned" label="Assigned to" :options="['me' => 'Me', 'none' => 'Unassigned'] + $staff->all()" :value="request('assigned')" placeholder="Anyone" />
        <x-select name="due" label="Follow-up" :options="['today' => 'Due today', 'overdue' => 'Overdue']" :value="request('due')" placeholder="Any" />
        <x-field name="from" type="date" label="Created from" :value="request('from')" />
        <x-field name="to" type="date" label="Created to" :value="request('to')" />
    </x-filters>

    @if ($view === 'board')
        <div class="kanban" data-kanban data-can-move="{{ auth()->user()->hasPerm('leads.update') ? 1 : 0 }}" role="list" aria-label="Lead pipeline">
            @foreach ($stages as $stage)
                @php($cards = $board[$stage->id] ?? collect())
                <section class="kanban-col" data-stage="{{ $stage->id }}" data-lost="{{ $stage->is_lost ? 1 : 0 }}" aria-labelledby="st-{{ $stage->id }}" role="listitem">
                    <header class="flex items-center justify-between px-3 py-2">
                        <h2 id="st-{{ $stage->id }}" class="text-sm font-bold">{{ $stage->name }}</h2>
                        <span class="badge-{{ $stage->color }}" data-count>{{ $cards->count() }}</span>
                    </header>
                    <ol class="kanban-list" data-drop>
                        @foreach ($cards as $l)
                            <li class="kanban-card" draggable="true" data-lead="{{ $l->id }}" data-url="{{ route('ws.leads.stage', $l) }}">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('ws.leads.show', $l) }}" class="font-semibold text-navy">{{ $l->name }}</a>
                                    <span class="{{ ['hot' => 'badge-red', 'warm' => 'badge-amber', 'cold' => 'badge-blue'][$l->priority] ?? 'badge-gray' }}">{{ \App\Models\Lead::PRIORITIES[$l->priority] ?? $l->priority }}</span>
                                </div>
                                <p class="mt-1 text-xs text-muted">{{ $l->sourceLabel() }}@if ($l->mobile) · {{ $l->mobile }}@endif</p>
                                @if ($l->interests->isNotEmpty())<p class="mt-1 truncate text-xs">{{ $l->interests->first()->label() }}</p>@endif
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <span class="{{ $l->isOverdue() ? 'font-bold text-red-700' : 'text-muted' }}">@if ($l->next_follow_up_at)<x-icon name="clock" class="inline h-3 w-3" /> {{ \App\Support\Format::datetime($l->next_follow_up_at) }}@endif</span>
                                    <span class="text-muted">{{ $l->assignee?->name ?? 'Unassigned' }}</span>
                                </div>
                                @can('leads.update')
                                    <form method="POST" action="{{ route('ws.leads.stage', $l) }}" class="mt-2 flex gap-1 kanban-move">
                                        @csrf
                                        <label class="sr-only" for="mv{{ $l->id }}">Move {{ $l->name }} to</label>
                                        <select id="mv{{ $l->id }}" name="stage_id" class="input py-1 text-xs">@foreach ($stages as $s)<option value="{{ $s->id }}" @selected($s->id === $l->lead_stage_id)>{{ $s->name }}</option>@endforeach</select>
                                        <button class="btn-light btn-sm">Move</button>
                                    </form>
                                @endcan
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-muted">Dropping a card on <strong>Lost</strong> opens the lead so you can choose the reason.</p>
    @else
        <div class="card"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>Lead</th><th>Source</th><th>Interested in</th><th>Stage</th><th>Next follow-up</th><th>Assigned</th></tr></thead>
            <tbody>
            @forelse ($leads as $l)
                <tr>
                    <td><a href="{{ route('ws.leads.show', $l) }}" class="font-semibold">{{ $l->name }}</a>
                        <span class="{{ ['hot' => 'badge-red', 'warm' => 'badge-amber', 'cold' => 'badge-blue'][$l->priority] ?? 'badge-gray' }} ml-1">{{ \App\Models\Lead::PRIORITIES[$l->priority] ?? '' }}</span>
                        <p class="text-xs text-muted">{{ $l->mobile }} {{ $l->email }} · {{ $l->lead_code }}</p></td>
                    <td class="text-sm">{{ $l->sourceLabel() }}<p class="text-xs text-muted">@date($l->created_at)</p></td>
                    <td class="max-w-xs text-sm"><p class="truncate">{{ $l->interests->map->label()->implode(', ') ?: '—' }}</p></td>
                    <td><span class="badge-{{ $l->stage?->color ?? 'gray' }}">{{ $l->stage?->name }}</span></td>
                    <td class="text-sm {{ $l->isOverdue() ? 'font-bold text-red-700' : '' }}">{{ $l->next_follow_up_at ? \App\Support\Format::datetime($l->next_follow_up_at) : '—' }}</td>
                    <td class="text-sm">{{ $l->assignee?->name ?? 'Unassigned' }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty title="No leads match" icon="chat">Leads appear here when buyers call, message, enquire, chat or book.</x-empty></td></tr>
            @endforelse
            </tbody>
        </table></div><div class="p-4">{{ $leads->links() }}</div></div>
    @endif
</x-layouts.workspace>
