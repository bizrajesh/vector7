<x-layouts.workspace title="Property listings">
    <x-page-header title="Property listings" subtitle="Individual plots, flats, farm land, commercial spaces, villas and houses. Buyers contact you — every contact becomes a lead.">
        @can('listings.export')<x-export-buttons />@endcan
        <a href="{{ route('help.show', 'listings') }}" class="btn-ghost" data-help><x-icon name="info" class="h-4 w-4" /> Help</a>
        @can('listings.create')<a href="{{ route('ws.listings.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> New listing</a>@endcan
    </x-page-header>
    <div class="card card-pad mb-4 flex flex-wrap items-center gap-4">
        <div class="min-w-48 flex-1">
            <div class="flex justify-between text-sm"><span class="font-semibold">Active listings on your plan</span><span>{{ $usage['display']['used'] }} / {{ $usage['display']['limit'] }}</span></div>
            @unless ($usage['unlimited'])<x-progress :pct="$usage['pct']" class="mt-1" label="Active listings used" />@endunless
        </div>
    </div>
    <x-filters>
        <x-field name="q" label="Search" :value="request('q')" placeholder="Title, listing ID, city" />
        <x-select name="type" label="Type" :options="\App\Listings\ListingTypes::options()" :value="request('type')" placeholder="All" />
        <x-select name="status" label="Status" :options="\App\Models\Listing::STATUSES" :value="request('status')" placeholder="All" />
    </x-filters>
    <div class="space-y-3">
        @forelse ($listings as $l)
            <div class="card flex flex-col gap-4 p-3 sm:flex-row sm:items-center">
                <a href="{{ route('ws.listings.show', $l) }}" class="block shrink-0">
                    @if ($l->cover)<img src="{{ $l->cover->url(true) }}" alt="" class="h-24 w-full rounded-lg object-cover sm:w-36" width="144" height="96" loading="lazy">
                    @else<span class="flex h-24 w-full items-center justify-center rounded-lg bg-page text-muted sm:w-36"><x-icon name="building" class="h-8 w-8" /></span>@endif
                </a>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('ws.listings.show', $l) }}" class="font-bold">{{ $l->title }}</a>
                        <span class="badge-gray">{{ $l->typeLabel() }}</span>
                        <span class="{{ ['published' => 'badge-teal', 'under_offer' => 'badge-amber', 'sold' => 'badge-navy', 'draft' => 'badge-gray', 'unpublished' => 'badge-gray'][$l->status] }}">{{ $l->statusLabel() }}</span>
                        @if ($l->hidden_by_admin)<span class="badge-red">Hidden by vector7</span>@endif
                    </div>
                    <p class="text-sm text-muted">{{ $l->listing_code }} · {{ $l->location() }} · {{ $l->purposeLabel() }} · {{ $l->images_count }} photo(s)</p>
                    <p class="mt-1 text-sm">@include('partials.price', ['item' => $l]) <span class="text-muted">· {{ implode(' · ', $l->specs()) }}</span></p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2 sm:flex-col sm:items-end">
                    <a href="{{ route('ws.leads.index', ['view' => 'list', 'listing' => 'L'.$l->id]) }}" class="text-sm">{{ $leadCounts[$l->id] ?? 0 }} lead(s)</a>
                    @can('listings.update')<a href="{{ route('ws.listings.edit', $l) }}" class="btn-light btn-sm">Edit</a>@endcan
                </div>
            </div>
        @empty
            <div class="card"><x-empty title="No listings yet" icon="building">Post a flat, house, villa, plot, farm land or commercial space — it appears on the marketplace once published.</x-empty></div>
        @endforelse
        {{ $listings->links() }}
    </div>
</x-layouts.workspace>
