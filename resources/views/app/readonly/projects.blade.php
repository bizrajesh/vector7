<x-layouts.workspace title="Projects">
    <x-page-header title="Projects — all tenants" subtitle="Read-only view of every promoter's projects with stage progress.">
        <x-export-buttons />
    </x-page-header>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Project name or code" />
        <x-select name="tenant" label="Tenant" :options="$tenants" :value="request('tenant')" placeholder="All tenants" />
        <x-select name="location" label="Location" :options="$locations->combine($locations)" :value="request('location')" placeholder="All" />
        <x-select name="status" label="Status" :options="\App\Models\Project::STATUSES" :value="request('status')" placeholder="All" />
        <x-select name="period" label="Timeline" :options="['month' => 'This month', 'quarter' => 'This quarter', 'year' => 'This financial year']" :value="request('period')" placeholder="Any time" />
    </x-filters>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Project</th><th>Tenant</th><th>Location</th><th>Approval</th><th class="num">Acres</th><th>Status</th><th class="w-40">Progress</th><th>Created</th></tr></thead>
        <tbody>
        @forelse ($projects as $p)
            <tr>
                <td><a href="{{ route('app.projects.show', $p) }}" class="font-semibold">{{ $p->name }}</a><p class="font-mono text-xs text-muted">{{ $p->project_code }}</p></td>
                <td>{{ $p->tenantRel?->name }}</td>
                <td>{{ $p->location }}<p class="text-xs text-muted">{{ $p->district }}</p></td>
                <td>{{ $p->approval_type }}</td>
                <td class="num">{{ \App\Support\Format::num($p->size_acres, 2) }}</td>
                <td><x-project-status :status="$p->status" /></td>
                <td><x-progress :pct="$p->progress_pct" level="teal" :label="'Progress of '.$p->name" /><p class="mt-1 text-xs text-muted">{{ round($p->progress_pct) }}%</p></td>
                <td class="text-xs">@date($p->created_at)</td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty title="No projects match" icon="folder" /></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="p-4">{{ $projects->links() }}</div></div>
</x-layouts.workspace>
