<x-layouts.workspace title="Enquiries">
    <x-page-header title="Enquiries" subtitle="Enquiries from the marketplace for your projects. Assign, set a follow-up date, and convert to a booking.">
        @can('enquiries.export')<x-export-buttons />@endcan
    </x-page-header>
    @if ($dueToday)
        <div class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200"><strong>{{ $dueToday }}</strong> follow-up(s) due today or earlier. <a href="{{ route('ws.enquiries.index', ['due' => 1]) }}">Show them</a></div>
    @endif
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Name, email, mobile" />
        <x-select name="project" label="Project" :options="$projects" :value="request('project')" placeholder="All" />
        <x-select name="status" label="Status" :options="\App\Models\Enquiry::STATUSES" :value="request('status')" placeholder="All" />
        <x-select name="assignee" label="Assigned" :options="['me' => 'Assigned to me']" :value="request('assignee')" placeholder="Anyone" />
    </x-filters>
    <div class="space-y-3">
        @forelse ($enquiries as $e)
            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-bold">{{ $e->name }}</p>
                        <p class="text-sm text-muted"><a href="mailto:{{ $e->email }}">{{ $e->email }}</a>@if ($e->mobile) · <a href="tel:{{ $e->mobile }}">{{ $e->mobile }}</a>@endif</p>
                        <p class="text-xs text-muted">@date($e->created_at)@if ($e->project) · {{ $e->project->name }}@endif @if ($e->plot) · Plot {{ $e->plot->plot_no }}@endif</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($e->follow_up_on && in_array($e->status, ['new', 'contacted']))<span class="{{ $e->follow_up_on->lte(today()) ? 'badge-red' : 'badge-blue' }}">Follow up @date($e->follow_up_on)</span>@endif
                        <span class="{{ ['new' => 'badge-amber', 'contacted' => 'badge-blue', 'converted' => 'badge-teal'][$e->status] ?? 'badge-gray' }}">{{ \App\Models\Enquiry::STATUSES[$e->status] }}</span>
                    </div>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm">{{ $e->message }}</p>
                @can('enquiries.update')
                    <form method="POST" action="{{ route('ws.enquiries.update', $e) }}" class="mt-3 grid items-end gap-3 border-t border-navy-50 pt-3 sm:grid-cols-2 xl:grid-cols-5">
                        @csrf @method('PUT')
                        <x-select name="assigned_to" label="Assign to" :options="$staff" :value="$e->assigned_to" placeholder="Unassigned" :id="'as'.$e->id" />
                        <x-select name="status" label="Status" :options="\App\Models\Enquiry::STATUSES" :value="$e->status" :id="'st'.$e->id" />
                        <x-field name="follow_up_on" type="date" label="Follow-up date" :value="$e->follow_up_on?->format('Y-m-d')" :id="'fu'.$e->id" />
                        <x-field name="booking_no" label="Booking no. (if converted)" :value="$e->booking_id ? \App\Models\Booking::find($e->booking_id)?->booking_no : null" :id="'bk'.$e->id" />
                        <button class="btn-primary btn-sm">Save</button>
                        <x-field name="notes" label="Notes" :value="$e->notes" :id="'nt'.$e->id" class="sm:col-span-2 xl:col-span-5" />
                    </form>
                @endcan
                @if ($e->status !== 'converted' && $e->plot_id)
                    @can('bookings.create')<a href="{{ route('ws.bookings.create', ['plot' => $e->plot_id]) }}" class="btn-teal btn-sm mt-3"><x-icon name="calendar" class="h-4 w-4" /> Book Plot {{ $e->plot?->plot_no }}</a>@endcan
                @endif
            </div>
        @empty
            <div class="card"><x-empty title="No enquiries" icon="chat" /></div>
        @endforelse
        {{ $enquiries->links() }}
    </div>
</x-layouts.workspace>
