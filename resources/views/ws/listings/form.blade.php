<x-layouts.workspace :title="$listing->exists ? 'Edit '.$listing->listing_code : 'New '.$type->label()">
    @include('partials.leaflet')
    <x-page-header :title="$listing->exists ? $listing->title : 'New '.$type->label()" :subtitle="$listing->exists ? $listing->listing_code.' · '.$type->label().' · '.$listing->statusLabel() : 'Saved as a draft first; publish when it has photos, price, location and a contact mode.'" :back="$listing->exists ? route('ws.listings.show', $listing) : route('ws.listings.create')">
        @if ($listing->exists)
            @if ($listing->isLive())<a href="{{ $listing->publicUrl() }}" target="_blank" rel="noopener" class="btn-light"><x-icon name="globe" class="h-4 w-4" /> View on marketplace</a>@endif
            @include('ws.listings.status-buttons')
        @endif
    </x-page-header>

    @if ($listing->exists && $problems && ! $listing->isLive())
        <div class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200"><strong>Before you can publish:</strong> {{ implode(' ', $problems) }}</div>
    @endif
    @if ($listing->hidden_by_admin)
        <div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200"><strong>Hidden by the vector7 team:</strong> {{ $listing->hidden_reason }}</div>
    @endif

    <form method="POST" action="{{ $listing->exists ? route('ws.listings.update', $listing) : route('ws.listings.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3">
        @csrf
        @if ($listing->exists) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $listing->type }}">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <div class="card card-pad grid gap-4 sm:grid-cols-2">
                <h2 class="section-title sm:col-span-2">Basics</h2>
                <x-field name="title" label="Title" :value="$listing->title" required maxlength="150" class="sm:col-span-2" :placeholder="['flat' => '2 BHK flat near Medical College Road', 'plot' => '1,200 sq ft east-facing plot, Vallam', 'farm_land' => '5 acres farm land with borewell', 'commercial' => 'Ground-floor shop on Gandhiji Road', 'villa' => '3 BHK villa in a gated community', 'house' => '2 BHK independent house, Arulananda Nagar'][$listing->type] ?? ''" />
                <x-select name="purpose" label="Purpose" :options="collect(\App\Listings\ListingTypes::PURPOSES)->only($type->purposes())->all()" :value="$listing->purpose" />
                <div class="flex items-end"><input type="hidden" name="negotiable" value="0"><x-checkbox name="negotiable" label="Price negotiable" :checked="(bool) $listing->negotiable" /></div>
                <div class="sm:col-span-2">
                    <div class="flex items-end justify-between gap-2">
                        <label for="f_description" class="label">Description</label>
                        <button type="button" class="btn-ghost btn-sm" data-ai-write="f_description" data-ai-kind="listing_description" data-url="{{ route('ws.ai.write') }}"><x-icon name="sparkles" class="h-4 w-4" /> Write with AI</button>
                    </div>
                    <textarea id="f_description" name="description" rows="6" class="input" maxlength="5000">{{ old('description', $listing->description) }}</textarea>
                    @error('description')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="card card-pad grid gap-4 sm:grid-cols-2">
                <h2 class="section-title sm:col-span-2">{{ $type->label() }} details</h2>
                @foreach ($type->fields() as $name => $f)
                    @php($v = old('details.'.$name, $listing->type_details[$name] ?? null))
                    @if ($f['kind'] === 'select')
                        <x-select :name="'details['.$name.']'" :label="$f['label']" :options="$f['options']" :value="$v" :placeholder="($f['required'] ?? false) ? null : '—'" :required="$f['required'] ?? false" />
                    @elseif ($f['kind'] === 'bool')
                        <div class="flex items-end"><input type="hidden" name="details[{{ $name }}]" value="0"><x-checkbox :name="'details['.$name.']'" :label="$f['label']" :checked="(bool) $v" /></div>
                    @else
                        <x-field :name="'details['.$name.']'" :label="$f['label'].(isset($f['unit']) ? ' ('.$f['unit'].')' : '')" :value="$v" :type="in_array($f['kind'], ['number', 'integer']) ? 'number' : 'text'" :step="$f['kind'] === 'number' ? 'any' : null" :min="$f['min'] ?? null" :required="$f['required'] ?? false" />
                    @endif
                @endforeach
            </div>

            <div class="card card-pad grid gap-4 sm:grid-cols-2">
                <h2 class="section-title sm:col-span-2">Location</h2>
                <x-field name="address_line1" label="Address line 1" :value="$listing->address_line1" class="sm:col-span-2" />
                <x-field name="area_name" label="Area / locality" :value="$listing->area_name" />
                <x-field name="city" label="Village / town / city" :value="$listing->city" required />
                <x-field name="district" label="District" :value="$listing->district" required />
                <x-field name="state" label="State" :value="$listing->state ?: 'Tamil Nadu'" required />
                <x-field name="pin" label="PIN" :value="$listing->pin" inputmode="numeric" maxlength="6" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="lat" label="Latitude" :value="$listing->lat" id="f_lat" inputmode="decimal" />
                    <x-field name="lng" label="Longitude" :value="$listing->lng" id="f_lng" inputmode="decimal" />
                </div>
                <div class="sm:col-span-2">
                    <p class="label">Map pin — tap the map to place it</p>
                    <div class="h-64 rounded-xl ring-1 ring-navy-50" data-map-picker data-lat="f_lat" data-lng="f_lng" role="application" aria-label="Map: tap to set the property location"></div>
                </div>
            </div>

            <div class="card card-pad grid gap-4 sm:grid-cols-2">
                <h2 class="section-title sm:col-span-2">Price & legal</h2>
                <x-field name="price" type="number" :label="$listing->purpose === 'rent' ? 'Rent per month (₹)' : 'Actual price (₹)'" :value="$listing->price ? (int) $listing->price : null" required min="1" />
                <x-field name="offer_price" type="number" label="Offer price (₹)" :value="$listing->offer_price ? (int) $listing->offer_price : null" min="1" hint="Must be lower than the actual price." />
                <x-field name="offer_text" label="Offer description" :value="$listing->offer_text" placeholder="Diwali offer – ₹2 lakh off" />
                <x-field name="offer_valid_till" type="date" label="Offer valid till" :value="$listing->offer_valid_till?->format('Y-m-d')" />
                <x-field name="patta_number" label="Patta number" :value="$listing->patta_number" :required="$type->requiresPatta()" :hint="$type->requiresPatta() ? 'Patta or survey number is required for this type.' : null" />
                <x-field name="survey_number" label="Survey number" :value="$listing->survey_number" />
                <x-field name="approval_number" label="Approval / RERA number" :value="$listing->approval_number" class="sm:col-span-2" hint="Shown as a trust badge when filled." />
            </div>

            <div class="card card-pad">@include('partials.property.features', ['features' => $features, 'selected' => $selected, 'highlights' => $highlights])</div>
            <div class="card card-pad">@include('partials.property.contact', ['contact' => $contact])</div>

            @unless ($listing->exists)
                <div class="card card-pad space-y-2">
                    <h2 class="section-title">Photos</h2>
                    <label class="dropzone" for="new_images"><x-icon name="upload" class="h-6 w-6" /><span><strong>Drop photos here</strong> or tap to choose · at least {{ \App\Services\PropertyMedia::MIN_IMAGES }} to publish, up to {{ \App\Services\PropertyMedia::MAX_IMAGES }} · JPG, PNG or WebP up to 5 MB</span>
                        <input id="new_images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" data-dropzone></label>
                    @error('images')<p class="error">{{ $message }}</p>@enderror
                    @error('images.*')<p class="error">{{ $message }}</p>@enderror
                    <p class="text-xs text-muted">You can reorder, caption and choose the cover after saving.</p>
                </div>
            @endunless
        </div>
        <div class="space-y-4">
            <div class="card card-pad sticky top-20 space-y-3">
                <button class="btn-primary w-full">{{ $listing->exists ? 'Save listing' : 'Save draft' }}</button>
                <p class="text-xs text-muted">Published listings appear on the marketplace straight away. Online booking is for layout plots only; buyers contact you about listings and each contact becomes a lead.</p>
            </div>
        </div>
    </form>

    @if ($listing->exists)
        <div class="mt-6">@include('partials.property.media', ['owner' => $listing, 'kind' => 'listing', 'canEdit' => auth()->user()->hasPerm('listings.update')])</div>
    @endif
</x-layouts.workspace>
