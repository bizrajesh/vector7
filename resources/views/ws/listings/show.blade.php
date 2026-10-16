<x-layouts.workspace :title="$listing->title">
    <x-page-header :title="$listing->title" :subtitle="$listing->listing_code.' · '.$listing->typeLabel().' · '.$listing->purposeLabel().' · '.$listing->location()" :back="route('ws.listings.index')">
        <span class="{{ ['published' => 'badge-teal', 'under_offer' => 'badge-amber', 'sold' => 'badge-navy'][$listing->status] ?? 'badge-gray' }}">{{ $listing->statusLabel() }}</span>
        @if ($listing->isLive())<a href="{{ $listing->publicUrl() }}" target="_blank" rel="noopener" class="btn-light"><x-icon name="globe" class="h-4 w-4" /> Marketplace page</a>@endif
        @can('listings.update')<a href="{{ route('ws.listings.edit', $listing) }}" class="btn-light"><x-icon name="edit" class="h-4 w-4" /> Edit</a>@endcan
        @include('ws.listings.status-buttons')
    </x-page-header>
    @if ($listing->hidden_by_admin)
        <div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200"><strong>Hidden by the vector7 team:</strong> {{ $listing->hidden_reason }}</div>
    @elseif ($problems && ! $listing->isLive())
        <div class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200"><strong>Before publishing:</strong> {{ implode(' ', $problems) }}</div>
    @endif
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            @if ($listing->images->isNotEmpty())
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($listing->images->take(8) as $img)
                        <img src="{{ $img->url(true) }}" alt="{{ $img->caption ?: $listing->title }}" class="aspect-[4/3] w-full rounded-lg object-cover {{ $img->is_cover ? 'ring-2 ring-teal' : '' }}" loading="lazy" width="400" height="300">
                    @endforeach
                </div>
            @endif
            <div class="card card-pad">
                <p>@include('partials.price', ['item' => $listing, 'big' => true]) @if ($listing->negotiable)<span class="badge-gray ml-2">Negotiable</span>@endif</p>
                @if ($listing->offerIsActive())<p class="text-sm text-teal-800">{{ $listing->offer_text }} · ends @date($listing->offer_valid_till)</p>@endif
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                    @foreach ($listing->type()->specTable($listing) as $label => $value)
                        <div><dt class="text-muted">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                    @endforeach
                    @if ($listing->patta_number)<div><dt class="text-muted">Patta</dt><dd class="font-semibold">{{ $listing->patta_number }}</dd></div>@endif
                    @if ($listing->survey_number)<div><dt class="text-muted">Survey no.</dt><dd class="font-semibold">{{ $listing->survey_number }}</dd></div>@endif
                    @if ($listing->approval_number)<div><dt class="text-muted">Approval / RERA</dt><dd class="font-semibold">{{ $listing->approval_number }}</dd></div>@endif
                </dl>
                @if ($listing->description)<p class="mt-4 whitespace-pre-line text-sm">{{ $listing->description }}</p>@endif
                @if ($listing->featureChips())
                    <div class="mt-4 flex flex-wrap gap-2">@foreach ($listing->featureChips() as $c)<span class="chip"><x-icon :name="$c['icon']" class="h-4 w-4" /> {{ $c['name'] }}</span>@endforeach</div>
                @endif
            </div>
            <div class="card">
                <div class="flex items-center justify-between p-4"><h2 class="section-title">Leads for this listing</h2>
                    @can('leads.view')<a href="{{ route('ws.leads.index', ['view' => 'list', 'listing' => 'L'.$listing->id]) }}" class="text-sm">Open in Leads</a>@endcan</div>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Lead</th><th>Source</th><th>Stage</th><th>Created</th></tr></thead>
                    <tbody>
                    @forelse ($leads as $l)
                        <tr><td><a href="{{ route('ws.leads.show', $l) }}">{{ $l->name }}</a><p class="text-xs text-muted">{{ $l->mobile }}</p></td><td class="text-sm">{{ $l->sourceLabel() }}</td>
                            <td><span class="badge-{{ $l->stage?->color ?? 'gray' }}">{{ $l->stage?->name }}</span></td><td class="text-xs">@date($l->created_at)</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-sm text-muted">No leads yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="space-y-6">
            <div class="card card-pad text-sm">
                <h2 class="section-title">Contact shown to buyers</h2>
                @if ($listing->contact)
                    <p class="mt-2 font-semibold">{{ $listing->contact->name }}</p>
                    <p>{{ $listing->contact->mobile }} @if ($listing->contact->whatsapp && $listing->contact->whatsapp !== $listing->contact->mobile)· WhatsApp {{ $listing->contact->whatsapp }}@endif</p>
                    <p>{{ $listing->contact->email }}</p>
                    <p class="mt-2 text-muted">Modes: {{ collect($listing->contact->modes())->map(fn ($m) => ['call' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'][$m])->implode(', ') ?: 'none' }}</p>
                @else
                    <p class="mt-2 text-muted">No contact set.</p>
                @endif
            </div>
            <div class="card card-pad text-sm">
                <h2 class="section-title">Stats</h2>
                <p class="mt-2">Views: <strong>{{ number_format($listing->views) }}</strong></p>
                <p>Published: {{ $listing->published_at ? \App\Support\Format::datetime($listing->published_at) : '—' }}</p>
            </div>
            @if ($listing->reports->isNotEmpty())
                <div class="card card-pad text-sm">
                    <h2 class="section-title">Reports from buyers</h2>
                    <ul class="mt-2 space-y-2">@foreach ($listing->reports as $r)<li><strong>{{ \App\Models\ListingReport::REASONS[$r->reason] ?? $r->reason }}</strong> · @date($r->created_at) <span class="badge-gray">{{ ucfirst($r->status) }}</span>@if ($r->details)<p class="text-muted">{{ $r->details }}</p>@endif</li>@endforeach</ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.workspace>
