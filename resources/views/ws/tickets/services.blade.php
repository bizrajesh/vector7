<x-layouts.workspace title="Services">
    <x-page-header title="vector7 services" subtitle="Legal, survey, registration, loan and marketing help from the vector7 team. Each request opens a help-desk ticket." :back="route('ws.tickets.index')" />
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($services as $s)
            <div class="card card-pad flex flex-col">
                <p class="font-bold">{{ $s->name }}</p>
                <p class="mt-1 text-lg font-extrabold text-teal-700">{{ $s->priceLabel() }}</p>
                <p class="mt-2 flex-1 text-sm text-muted">{{ $s->summary ?: \Illuminate\Support\Str::limit($s->description, 160) }}</p>
                @can('tickets.create')
                    <details class="mt-3"><summary class="btn-teal btn-sm cursor-pointer list-none">Request this service</summary>
                        <form method="POST" action="{{ route('ws.services.request', $s) }}" class="mt-3 space-y-2">
                            @csrf
                            <x-textarea name="notes" label="What do you need?" rows="3" required :id="'sv'.$s->id" placeholder="Project, survey numbers, timeline…" />
                            <button class="btn-primary btn-sm">Send request</button>
                        </form>
                    </details>
                @endcan
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty title="No services available right now" icon="briefcase" /></div>
        @endforelse
    </div>
    @if ($requests->isNotEmpty())
        <div class="card mt-6">
            <h2 class="section-title p-4">Your requests</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Date</th><th>Service</th><th>Ticket</th></tr></thead>
                <tbody>@foreach ($requests as $r)<tr><td class="text-xs">@date($r->created_at)</td><td>{{ $r->service?->name }}</td><td>@if ($r->ticket)<a href="{{ route('ws.tickets.show', $r->ticket) }}" class="font-mono text-xs">{{ $r->ticket->number }}</a> <span class="badge-gray">{{ \App\Models\Ticket::STATUSES[$r->ticket->status] ?? '' }}</span>@endif</td></tr>@endforeach</tbody>
            </table></div>
        </div>
    @endif
</x-layouts.workspace>
