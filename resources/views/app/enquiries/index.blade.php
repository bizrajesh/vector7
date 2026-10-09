<x-layouts.workspace title="Enquiries">
    <x-page-header title="Enquiries" subtitle="Every enquiry from the marketplace. Hand each one to the promoter or keep it with the App team.">
        <x-export-buttons />
    </x-page-header>
    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (\App\Models\Enquiry::STATUSES as $k => $label)
            <x-stat :label="$label" :value="$counts[$k] ?? 0" :icon="['new' => 'bell', 'contacted' => 'phone', 'converted' => 'check', 'closed' => 'x'][$k]" :tone="$k === 'new' ? 'gold' : 'teal'" />
        @endforeach
    </div>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Name, email, mobile" />
        <x-select name="tenant" label="Assigned to" :options="['app' => 'App team'] + $tenants->all()" :value="request('tenant')" placeholder="Anyone" />
        <x-select name="status" label="Status" :options="\App\Models\Enquiry::STATUSES" :value="request('status')" placeholder="All" />
    </x-filters>
    <div class="space-y-3">
        @forelse ($enquiries as $e)
            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-bold">{{ $e->name }} <span class="text-sm font-normal text-muted">· {{ $e->email }} · {{ $e->mobile }}</span></p>
                        <p class="text-xs text-muted">@date($e->created_at) · {{ $e->tenantRel?->name ?? 'No promoter' }}@if ($e->project) · {{ $e->project->name }}@endif @if ($e->plot) · Plot {{ $e->plot->plot_no }}@endif</p>
                    </div>
                    <span class="{{ $e->status === 'new' ? 'badge-amber' : 'badge-gray' }}">{{ \App\Models\Enquiry::STATUSES[$e->status] }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm">{{ $e->message }}</p>
                @can('app_enquiries.update')
                    <form method="POST" action="{{ route('app.enquiries.update', $e) }}" class="mt-3 grid gap-3 border-t border-navy-50 pt-3 sm:grid-cols-2 xl:grid-cols-6 items-end">
                        @csrf @method('PUT')
                        <x-select name="assigned_team" label="Team" :options="['tenant' => 'Promoter', 'app' => 'App team']" :value="$e->assigned_team" :id="'t'.$e->id" />
                        <x-select name="tenant_id" label="Promoter" :options="$tenants" :value="$e->tenant_id" placeholder="—" :id="'tn'.$e->id" />
                        <x-select name="assigned_to" label="App assignee" :options="$appUsers" :value="$e->assigned_team === 'app' ? $e->assigned_to : null" placeholder="—" :id="'a'.$e->id" />
                        <x-select name="status" label="Status" :options="\App\Models\Enquiry::STATUSES" :value="$e->status" :id="'s'.$e->id" />
                        <x-field name="follow_up_on" type="date" label="Follow up" :value="$e->follow_up_on?->format('Y-m-d')" :id="'f'.$e->id" />
                        <button class="btn-primary btn-sm">Save</button>
                        <x-field name="notes" label="Notes" :value="$e->notes" :id="'n'.$e->id" class="sm:col-span-2 xl:col-span-6" />
                    </form>
                @endcan
            </div>
        @empty
            <div class="card"><x-empty title="No enquiries" icon="chat" /></div>
        @endforelse
        {{ $enquiries->links() }}
    </div>
</x-layouts.workspace>
