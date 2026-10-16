<x-layouts.workspace title="Listings moderation">
    <x-page-header title="Listings moderation" subtitle="All listings and layouts on the marketplace. Hide listings that break the rules (the promoter is emailed the reason), review buyer reports, pick featured items and approve testimonials." />
    <nav class="tabs mb-6" aria-label="Moderation">
        @foreach (['listings' => 'Listings', 'reports' => 'Buyer reports', 'layouts' => 'Featured layouts', 'testimonials' => 'Testimonials'] as $k => $l)
            <a href="{{ route('app.listings.index', ['tab' => $k]) }}" class="tab {{ $tab === $k ? 'active' : '' }}" @if ($tab === $k) aria-current="page" @endif>{{ $l }}</a>
        @endforeach
    </nav>
    @php($canEdit = auth()->user()->hasPerm('app_listings.update'))

    @if ($tab === 'listings')
        <x-filters>
            <input type="hidden" name="tab" value="listings">
            <x-field name="q" label="Search" :value="request('q')" placeholder="Title, listing ID, city" />
            <x-select name="tenant" label="Promoter" :options="$tenants" :value="request('tenant')" placeholder="All" />
            <x-select name="type" label="Type" :options="\App\Listings\ListingTypes::options()" :value="request('type')" placeholder="All" />
            <x-select name="status" label="Status" :options="\App\Models\Listing::STATUSES + ['hidden' => 'Hidden by vector7']" :value="request('status')" placeholder="All" />
        </x-filters>
        <div class="space-y-3">
            @forelse ($listings as $l)
                <div class="card flex flex-col gap-3 p-3 lg:flex-row lg:items-center">
                    @if ($l->cover)<img src="{{ $l->cover->url(true) }}" alt="" class="h-20 w-full rounded-lg object-cover lg:w-28" width="112" height="80" loading="lazy">@endif
                    <div class="min-w-0 flex-1">
                        <p class="font-bold">{{ $l->title }} <span class="badge-gray">{{ $l->typeLabel() }}</span> <span class="badge-navy">{{ $l->statusLabel() }}</span>
                            @if ($l->hidden_by_admin)<span class="badge-red">Hidden</span>@endif @if ($l->featured)<span class="badge-amber">Featured</span>@endif @if ($l->reports_count)<span class="badge-red">{{ $l->reports_count }} open report(s)</span>@endif</p>
                        <p class="text-sm text-muted">{{ $l->listing_code }} · {{ $l->tenantRel?->name }} · {{ $l->location() }} · @inr($l->currentPrice())</p>
                        @if ($l->hidden_reason)<p class="text-sm text-red-800">Reason: {{ $l->hidden_reason }}</p>@endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($l->isLive())<a href="{{ $l->publicUrl() }}" target="_blank" rel="noopener" class="btn-ghost btn-sm">View</a>@endif
                        @if ($canEdit)
                            <form method="POST" action="{{ route('app.listings.feature', ['listing', $l->id]) }}">@csrf<button class="btn-light btn-sm">{{ $l->featured ? 'Unfeature' : 'Feature' }}</button></form>
                            @if ($l->hidden_by_admin)
                                <form method="POST" action="{{ route('app.listings.unhide', $l) }}">@csrf<button class="btn-light btn-sm">Restore</button></form>
                            @else
                                <details class="relative" data-dropdown><summary class="btn-light btn-sm list-none cursor-pointer text-red-700">Hide</summary>
                                    <form method="POST" action="{{ route('app.listings.hide', $l) }}" class="absolute right-0 z-20 mt-2 w-80 space-y-2 rounded-xl bg-white p-4 shadow-lift ring-1 ring-navy-50">@csrf
                                        <x-textarea name="reason" label="Reason (emailed to the promoter)" rows="3" required :id="'hr'.$l->id" />
                                        <button class="btn-primary btn-sm">Hide listing</button>
                                    </form>
                                </details>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="card"><x-empty title="No listings" icon="building" /></div>
            @endforelse
            {{ $listings->links() }}
        </div>
    @elseif ($tab === 'reports')
        <div class="mb-4 flex gap-2">@foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $lbl)<a href="{{ route('app.listings.index', ['tab' => 'reports', 'status' => $k]) }}" class="{{ request('status', 'open') === $k ? 'btn-primary' : 'btn-light' }} btn-sm">{{ $lbl }}</a>@endforeach</div>
        <div class="card"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>Date</th><th>Listing</th><th>Reason</th><th>From</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($reports as $r)
                <tr>
                    <td class="text-xs">@date($r->created_at)</td>
                    <td>{{ $r->listing?->title }}<p class="text-xs text-muted">{{ $r->listing?->listing_code }} · {{ $r->listing?->tenantRel?->name }}</p></td>
                    <td><strong>{{ \App\Models\ListingReport::REASONS[$r->reason] ?? $r->reason }}</strong>@if ($r->details)<p class="text-xs">{{ $r->details }}</p>@endif</td>
                    <td class="text-sm">{{ $r->customer?->name ?? 'Visitor' }}</td>
                    <td><span class="badge-gray">{{ ucfirst($r->status) }}</span></td>
                    <td class="whitespace-nowrap text-right">@if ($canEdit && $r->status === 'open')
                        <form method="POST" action="{{ route('app.listings.reports.resolve', $r) }}" class="inline">@csrf<input type="hidden" name="status" value="resolved"><button class="btn-light btn-sm">Resolved</button></form>
                        <form method="POST" action="{{ route('app.listings.reports.resolve', $r) }}" class="inline">@csrf<input type="hidden" name="status" value="dismissed"><button class="btn-ghost btn-sm">Dismiss</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty title="No reports" icon="shield" /></td></tr>
            @endforelse
            </tbody>
        </table></div><div class="p-4">{{ $reports->links() }}</div></div>
    @elseif ($tab === 'layouts')
        <div class="card"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>Layout</th><th>Promoter</th><th class="num">Available plots</th><th>Featured</th><th></th></tr></thead>
            <tbody>
            @foreach ($layouts as $p)
                <tr><td><a href="{{ $p->publicUrl() }}" target="_blank" rel="noopener">{{ $p->name }}</a><p class="text-xs text-muted">{{ $p->location }}, {{ $p->district }}{{ $p->isDirect() ? ' · Direct launch' : '' }}</p></td>
                    <td>{{ $p->tenantRel?->name }}</td><td class="num">{{ $p->available_count }}</td><td>@if ($p->is_featured)<span class="badge-amber">Featured</span>@endif</td>
                    <td class="text-right">@if ($canEdit)<form method="POST" action="{{ route('app.listings.feature', ['project', $p->id]) }}">@csrf<button class="btn-light btn-sm">{{ $p->is_featured ? 'Unfeature' : 'Feature' }}</button></form>@endif</td></tr>
            @endforeach
            </tbody>
        </table></div><div class="p-4">{{ $layouts->links() }}</div></div>
    @else
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="min-w-0 space-y-3 xl:col-span-2">
                @forelse ($testimonials as $t)
                    <div class="card card-pad">
                        <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-bold">{{ $t->name }} <span class="text-sm font-normal text-muted">{{ $t->place }}</span></p>
                            <span class="{{ ['approved' => 'badge-teal', 'rejected' => 'badge-red'][$t->status] ?? 'badge-amber' }}">{{ ucfirst($t->status) }}</span></div>
                        <p class="mt-2 text-sm">“{{ $t->body }}”</p>
                        @if ($canEdit)
                            <div class="mt-3 flex gap-2">
                                @foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $st => $lbl)
                                    @if ($t->status !== $st)<form method="POST" action="{{ route('app.testimonials.update', $t) }}">@csrf<input type="hidden" name="status" value="{{ $st }}"><button class="btn-light btn-sm">{{ $lbl }}</button></form>@endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="card"><x-empty title="No testimonials yet" icon="star">Buyers can share their experience from their account. Only approved, real testimonials appear on the home page.</x-empty></div>
                @endforelse
                {{ $testimonials->links() }}
            </div>
            @if ($canEdit)
                <form method="POST" action="{{ route('app.testimonials.store') }}" class="card card-pad h-fit space-y-3">@csrf
                    <h2 class="section-title">Add a testimonial you received</h2>
                    <x-field name="name" label="Buyer name" required />
                    <x-field name="place" label="Place" />
                    <x-textarea name="body" label="What they said" rows="4" required />
                    <x-select name="rating" label="Rating" :options="[5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']" placeholder="—" />
                    <button class="btn-primary">Add (pending)</button>
                </form>
            @endif
        </div>
    @endif
</x-layouts.workspace>
