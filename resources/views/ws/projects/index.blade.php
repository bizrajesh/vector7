<x-layouts.workspace title="Projects">
    <x-page-header title="Projects" subtitle="Draft → Init → Go / No-Go → In Progress → Ready to Launch → Launched">
        @can('projects.export')<x-export-buttons />@endcan
        @can('projects.create')<a href="{{ route('ws.projects.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> New project</a>@endcan
    </x-page-header>
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('ws.projects.index') }}" class="{{ request('status') ? 'btn-light' : 'btn-primary' }} btn-sm">All</a>
        @foreach (\App\Models\Project::STATUSES as $k => $l)
            <a href="{{ route('ws.projects.index', ['status' => $k]) }}" class="{{ request('status') === $k ? 'btn-primary' : 'btn-light' }} btn-sm">{{ $l }} <span class="opacity-70">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Name, code or location" />
        <x-select name="type" label="Approval type" :options="array_combine(\App\Models\Project::APPROVAL_TYPES, \App\Models\Project::APPROVAL_TYPES)" :value="request('type')" placeholder="All" />
        <x-select name="location" label="Location" :options="$locations->combine($locations)" :value="request('location')" placeholder="All" />
        <input type="hidden" name="status" value="{{ request('status') }}">
    </x-filters>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($projects as $p)
            <a href="{{ route('ws.projects.show', $p) }}" class="card card-pad block text-navy no-underline transition hover:-translate-y-0.5 hover:shadow-lift">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0"><p class="truncate text-lg font-extrabold">{{ $p->name }}</p><p class="text-xs text-muted">{{ $p->project_code }} · {{ $p->approval_type }} · {{ $p->location }}</p></div>
                    <x-project-status :status="$p->status" />
                </div>
                <div class="mt-4 flex items-center gap-2"><x-progress :pct="$p->progress_pct" level="teal" class="flex-1" label="Progress" /><span class="text-xs font-semibold">{{ (float) $p->progress_pct }}%</span></div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-xs text-muted">Size</dt><dd class="font-semibold">{{ (float) $p->size_acres }} acres</dd></div>
                    <div><dt class="text-xs text-muted">Budget</dt><dd class="font-semibold">{{ $p->estimate ? \App\Support\Format::inrShort($p->estimate->total_cost) : '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">Start</dt><dd>@date($p->start_date)</dd></div>
                    <div><dt class="text-xs text-muted">Est. end</dt><dd>@date($p->est_end_date)</dd></div>
                </dl>
            </a>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty title="No projects yet" icon="folder">Create your first project to start the estimate and approval tracking.</x-empty></div>
        @endforelse
    </div>
    {{ $projects->links() }}
</x-layouts.workspace>
