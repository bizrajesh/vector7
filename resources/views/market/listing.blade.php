<x-layouts.public :seo="$seo" :jsonld="$jsonld">
    @if ($listing->lat !== null && $listing->lng !== null)@include('partials.leaflet')@endif
    @php($typePlural = $listing->type()->plural())
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            <nav class="text-sm text-muted" aria-label="{{ __('Breadcrumb') }}">
                <a href="{{ route('home') }}">{{ __('Home') }}</a> /
                <a href="{{ \App\Services\PropertySearch::categoryUrl($listing->type, $listing->purpose) }}">{{ $typePlural }} {{ $listing->purpose === 'rent' ? 'for rent' : 'for sale' }}</a> /
                <a href="{{ \App\Services\PropertySearch::categoryUrl($listing->type, $listing->purpose, $listing->city) }}">{{ $listing->city }}</a> /
                <span aria-current="page">{{ $listing->listing_code }}</span>
            </nav>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-6">
                <div class="min-w-0">
                    <p class="flex flex-wrap gap-2 text-xs font-bold">
                        <span class="badge-navy">{{ $listing->typeLabel() }} · {{ $listing->purposeLabel() }}</span>
                        @if ($listing->status === 'under_offer')<span class="badge-amber">{{ __('Under offer') }}</span>@endif
                        @if ($listing->approval_number)<span class="badge-teal"><x-icon name="shield" class="inline h-3.5 w-3.5" /> {{ __('Approval :no', ['no' => $listing->approval_number]) }}</span>@endif
                    </p>
                    <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $listing->title }}</h1>
                    <p class="mt-1 flex items-center gap-1 text-muted"><x-icon name="location" class="h-4 w-4" /> {{ $listing->location() }}</p>
                </div>
                <div class="text-right">
                    <p>@include('partials.price', ['item' => $listing, 'big' => true])</p>
                    @if ($listing->offerIsActive())<p class="text-sm font-semibold text-teal-800">{{ $listing->offer_text ?: __('Offer price') }} · {{ __('ends :date (:days days left)', ['date' => \App\Support\Format::date($listing->offer_valid_till), 'days' => $listing->offerDaysLeft()]) }}</p>@endif
                    @if ($listing->pricePerSqft())<p class="text-sm text-muted">{{ __(':price per sq ft', ['price' => \App\Support\Format::inr($listing->pricePerSqft())]) }}</p>@endif
                    @if ($listing->negotiable)<p class="text-sm text-muted">{{ __('Price negotiable') }}</p>@endif
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-8 lg:col-span-2">
            @if ($listing->images->isNotEmpty())
                <section aria-label="{{ __('Photos') }}" data-gallery>
                    @php($cover = $listing->images->first())
                    <a href="{{ $cover->url() }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-2xl bg-page">
                        <img src="{{ $cover->url() }}" alt="{{ $cover->caption ?: $listing->title }}" class="aspect-[16/10] w-full object-cover" width="{{ $cover->width ?: 1600 }}" height="{{ $cover->height ?: 1000 }}" fetchpriority="high">
                    </a>
                    @if ($listing->images->count() > 1)
                        <ul class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-6">
                            @foreach ($listing->images->skip(1) as $img)
                                <li><a href="{{ $img->url() }}" target="_blank" rel="noopener"><img src="{{ $img->url(true) }}" alt="{{ $img->caption ?: $listing->title.' photo '.$loop->iteration }}" class="aspect-[4/3] w-full rounded-lg object-cover" loading="lazy" width="400" height="300"></a></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            <section class="card card-pad" aria-labelledby="det-h">
                <h2 id="det-h" class="text-xl font-extrabold">{{ __('Details') }}</h2>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                    @php($specTable = $listing->type()->specTable($listing))
                    @foreach ($specTable as $label => $value)
                        <div><dt class="text-muted">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                    @endforeach
                    @if ($listing->facing && ! isset($specTable['Facing']))<div><dt class="text-muted">{{ __('Facing') }}</dt><dd class="font-semibold">{{ $listing->facing }}</dd></div>@endif
                    @if ($listing->patta_number)<div><dt class="text-muted">{{ __('Patta number') }}</dt><dd class="font-semibold">{{ $listing->patta_number }}</dd></div>@endif
                    @if ($listing->survey_number)<div><dt class="text-muted">{{ __('Survey number') }}</dt><dd class="font-semibold">{{ $listing->survey_number }}</dd></div>@endif
                    <div><dt class="text-muted">{{ __('Listing ID') }}</dt><dd class="font-semibold">{{ $listing->listing_code }}</dd></div>
                    @if ($listing->published_at)<div><dt class="text-muted">{{ __('Listed on') }}</dt><dd class="font-semibold">@date($listing->published_at)</dd></div>@endif
                </dl>
                @if ($listing->description)<div class="mt-5 max-w-prose whitespace-pre-line leading-relaxed">{{ $listing->description }}</div>@endif
            </section>

            @if ($listing->featureChips())
                <section class="card card-pad" aria-labelledby="feat-h">
                    <h2 id="feat-h" class="text-xl font-extrabold">{{ __('Features') }}</h2>
                    <ul class="mt-3 flex flex-wrap gap-2">@foreach ($listing->featureChips() as $c)<li class="chip"><x-icon :name="$c['icon']" class="h-4 w-4" /> {{ __($c['name']) }}</li>@endforeach</ul>
                </section>
            @endif

            @if ($youtubeId)
                <section aria-labelledby="vid-h"><h2 id="vid-h" class="text-xl font-extrabold">{{ __('Video') }}</h2>
                    <div class="mt-3 aspect-video overflow-hidden rounded-2xl bg-navy">
                        <iframe src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}" title="{{ __('Video of :name', ['name' => $listing->title]) }}" class="h-full w-full" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    </div>
                </section>
            @endif

            @if ($listing->lat !== null && $listing->lng !== null)
                <section aria-labelledby="loc-h"><h2 id="loc-h" class="text-xl font-extrabold">{{ __('Location') }}</h2>
                    <div class="mt-3 h-72 overflow-hidden rounded-2xl ring-1 ring-navy-50" data-map-pins="listing-pin" role="application" aria-label="{{ __('Map showing the property location') }}"></div>
                    <script type="application/json" id="listing-pin" nonce="{{ $cspNonce ?? '' }}">{!! json_encode([['lat' => (float) $listing->lat, 'lng' => (float) $listing->lng, 'title' => $listing->title, 'type' => $listing->typeLabel(), 'price' => null, 'url' => null]], JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
                    <a href="https://www.openstreetmap.org/?mlat={{ $listing->lat }}&mlon={{ $listing->lng }}#map=16/{{ $listing->lat }}/{{ $listing->lng }}" class="mt-2 inline-block text-sm" target="_blank" rel="noopener nofollow">{{ __('Open in a full map') }}</a>
                </section>
            @endif

            @if ($similar->isNotEmpty())
                <section aria-labelledby="sim-h"><h2 id="sim-h" class="text-xl font-extrabold">{{ __('Similar properties') }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">@foreach ($similar as $s)@include('partials.property-card', ['item' => $s, 'shortlisted' => []])@endforeach</div>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            @if ($contact)
                @include('partials.market-contact', ['kind' => 'listing', 'ownerId' => $listing->id, 'c' => \App\Http\Controllers\Market\ContactController::contactArray($contact), 'title' => $listing->title, 'src' => $src])
            @endif
            <section class="card card-pad space-y-3 text-sm">
                @auth('customer')
                    <form method="POST" action="{{ route('account.shortlist.toggle') }}">@csrf<input type="hidden" name="key" value="L:{{ $listing->id }}">
                        <button class="btn-light w-full"><x-icon name="star" class="h-4 w-4" /> {{ $shortlisted ? 'Remove from shortlist' : 'Add to shortlist' }}</button></form>
                @else
                    <a href="{{ route('login') }}" class="btn-light w-full"><x-icon name="star" class="h-4 w-4" /> {{ __('Sign in to shortlist') }}</a>
                @endauth
                <a href="{{ route('market.compare', ['keys' => 'L:'.$listing->id]) }}" class="btn-ghost w-full">{{ __('Compare with others') }}</a>
                <div class="grid grid-cols-2 gap-2">
                    <a href="https://wa.me/?text={{ rawurlencode($listing->title.' – '.$listing->publicUrl()) }}" class="btn-light btn-sm" target="_blank" rel="noopener"><x-icon name="chat" class="h-4 w-4" /> {{ __('Share') }}</a>
                    <button type="button" class="btn-light btn-sm" data-copy-link="{{ $listing->publicUrl() }}"><x-icon name="link" class="h-4 w-4" /> {{ __('Copy link') }}</button>
                </div>
                @if ($listing->brochure)<a href="{{ route('market.brochure', ['listing', $listing->id]) }}" class="btn-ghost w-full"><x-icon name="download" class="h-4 w-4" /> {{ __('Download brochure (PDF)') }}</a>@endif
                <p class="text-muted">{{ __('Listed by') }} <strong>{{ $tenant->name }}</strong>@if ($tenant->city), {{ $tenant->city }}@endif</p>
                <p class="text-muted">{{ trans_choice(':count view|:count views', $listing->views, ['count' => number_format($listing->views)]) }}</p>
            </section>
            <details class="card card-pad text-sm">
                <summary class="cursor-pointer font-semibold"><x-icon name="alert" class="inline h-4 w-4" /> {{ __('Report this listing') }}</summary>
                <form method="POST" action="{{ route('market.listing.report', $listing) }}" class="mt-3 space-y-3">
                    @csrf
                    <x-select name="reason" :label="__('What is wrong?')" :options="\App\Models\ListingReport::REASONS" id="r_reason" />
                    <x-textarea name="details" :label="__('Details (optional)')" rows="2" id="r_details" />
                    <button class="btn-light w-full">{{ __('Send report') }}</button>
                </form>
            </details>
        </aside>
    </div>
</x-layouts.public>
