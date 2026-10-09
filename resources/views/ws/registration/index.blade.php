<x-layouts.workspace title="Registration">
    <x-page-header title="Registration" subtitle="ROR → ROR-Init → ROR-Completed → Sold" />
    <x-filters>
        <x-select name="project" label="Project" :options="$projects" :value="request('project')" placeholder="All" />
        <x-select name="location" label="Location" :options="$locations->combine($locations)" :value="request('location')" placeholder="All" />
        <x-select name="status" label="Registration status" :options="\App\Models\Registration::STATUSES" :value="request('status')" placeholder="All" />
    </x-filters>
    <div class="card mb-6">
        <h2 class="section-title p-4">Ready for registration (ROR) — {{ $ror->count() }}</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Plot</th><th>Location</th><th>Buyer</th><th class="num">Paid</th><th></th></tr></thead>
            <tbody>@forelse ($ror as $s)
                <tr><td class="font-semibold">Plot {{ $s->plot->plot_no }} · {{ $s->project->name }}</td><td>{{ $s->project->location }}</td><td>{{ $s->customer->name }}</td><td class="num">@inr($s->paid_amount)</td>
                    <td class="text-right">@can('registration.create')<a href="{{ route('ws.registrations.create', $s) }}" class="btn-teal btn-sm">Init registration</a>@endcan</td></tr>
            @empty<tr><td colspan="5" class="text-muted">No fully paid plots waiting.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
    <div class="card">
        <h2 class="section-title p-4">Registrations</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Plot</th><th>Buyer</th><th>Date</th><th>SRO</th><th>Status</th><th></th></tr></thead>
            <tbody>@forelse ($registrations as $r)
                <tr><td class="font-semibold">Plot {{ $r->plot->plot_no }} · {{ $r->project->name }}</td><td>{{ $r->customer->name }}</td><td>@date($r->registration_date)</td><td class="text-sm">{{ $r->sro?->name }}</td>
                    <td><x-status :status="$r->plot->status" /></td><td class="text-right"><a href="{{ route('ws.registrations.show', $r) }}" class="btn-light btn-sm">Open</a></td></tr>
            @empty<tr><td colspan="6" class="text-muted">No registrations yet.</td></tr>@endforelse</tbody>
        </table></div>
        <div class="p-4">{{ $registrations->links() }}</div>
    </div>
</x-layouts.workspace>
