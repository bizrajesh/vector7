<x-layouts.workspace title="Services">
    <x-page-header title="Services" subtitle="Services vector7 offers to promoters and buyers. Requests arrive in the Help desk as tickets." />
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-4 xl:col-span-2">
            @forelse ($services as $s)
                <div class="card card-pad">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-bold">{{ $s->name }} @unless ($s->is_active)<span class="badge-gray ml-1">Inactive</span>@endunless</p>
                            <p class="text-sm text-muted">{{ $s->summary }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-teal-700">{{ $s->priceLabel() }}</p>
                            <p class="text-xs text-muted">For {{ ['tenant' => 'promoters', 'customer' => 'buyers', 'both' => 'promoters & buyers'][$s->available_to] }}</p>
                        </div>
                    </div>
                    @canany(['services.update', 'services.delete'])
                        <details class="mt-3"><summary class="cursor-pointer text-sm font-semibold text-teal-700">Edit</summary>
                            @can('services.update')
                                <form method="POST" action="{{ route('app.services.update', $s) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @csrf @method('PUT')
                                    @include('app.services.fields', ['s' => $s, 'p' => 's'.$s->id])
                                    <div class="sm:col-span-2"><button class="btn-primary btn-sm">Save</button></div>
                                </form>
                            @endcan
                            @can('services.delete')<div class="mt-2"><x-confirm :action="route('app.services.destroy', $s)" method="DELETE" message="Delete {{ $s->name }}? Services with requests are deactivated instead." class="btn-ghost btn-sm text-red-700">Delete</x-confirm></div>@endcan
                        </details>
                    @endcanany
                </div>
            @empty
                <div class="card"><x-empty title="No services yet" icon="briefcase" /></div>
            @endforelse

            <div class="card">
                <h2 class="section-title p-4">Recent requests</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Date</th><th>Service</th><th>Requested by</th><th>Ticket</th></tr></thead>
                    <tbody>
                    @forelse ($requests as $r)
                        <tr>
                            <td class="text-xs">@date($r->created_at)</td>
                            <td>{{ $r->service?->name }}</td>
                            <td>{{ $r->customer?->name ?? $r->user?->name }} <span class="text-xs text-muted">{{ $r->tenant?->name ? '· '.$r->tenant->name : '(buyer)' }}</span></td>
                            <td>@if ($r->ticket)<a href="{{ route('app.tickets.show', $r->ticket) }}" class="font-mono text-xs">{{ $r->ticket->number }}</a> <span class="badge-gray">{{ \App\Models\Ticket::STATUSES[$r->ticket->status] ?? '' }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-sm text-muted">No requests yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        @can('services.create')
            <form method="POST" action="{{ route('app.services.store') }}" class="card card-pad grid h-fit gap-3">
                @csrf
                <h2 class="section-title">Add a service</h2>
                @include('app.services.fields', ['s' => new \App\Models\Service(['available_to' => 'both', 'is_active' => true]), 'p' => 'new'])
                <button class="btn-primary">Add service</button>
            </form>
        @endcan
    </div>
</x-layouts.workspace>
