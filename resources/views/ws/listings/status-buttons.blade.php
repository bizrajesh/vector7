@can('listings.update')
    @if (in_array($listing->status, ['draft', 'unpublished']))
        <form method="POST" action="{{ route('ws.listings.status', $listing) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn-primary"><x-icon name="rocket" class="h-4 w-4" /> Publish</button></form>
    @elseif ($listing->status === 'published')
        <form method="POST" action="{{ route('ws.listings.status', $listing) }}">@csrf<input type="hidden" name="status" value="under_offer"><button class="btn-light">Under offer</button></form>
    @elseif ($listing->status === 'under_offer')
        <form method="POST" action="{{ route('ws.listings.status', $listing) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn-light">Back to published</button></form>
    @endif
    @if ($listing->isLive())
        <x-confirm :action="route('ws.listings.status', $listing)" :message="'Mark this listing '.($listing->purpose === 'rent' ? 'Rented' : 'Sold').'? It leaves the marketplace.'" class="btn-light" :fields="['status' => 'sold']">{{ $listing->purpose === 'rent' ? 'Mark rented' : 'Mark sold' }}</x-confirm>
        <x-confirm :action="route('ws.listings.status', $listing)" message="Unpublish this listing? It is hidden from the marketplace until you publish it again." class="btn-ghost" :fields="['status' => 'unpublished']">Unpublish</x-confirm>
    @endif
@endcan
