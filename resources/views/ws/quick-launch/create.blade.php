<x-layouts.workspace title="Quick launch layout">
    @include('partials.leaflet')
    <x-page-header title="Quick launch a layout" subtitle="Already have an approved layout? Publish it in one go — no estimate, Go/No-Go or stage tracking. Booking, sales, payments and registration work as usual." :back="route('ws.projects.index')">
        <a href="{{ route('ws.launch.sample') }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Sample plots CSV</a>
        <a href="{{ route('help.show', 'quick-launch') }}" class="btn-ghost" data-help><x-icon name="info" class="h-4 w-4" /> Help</a>
    </x-page-header>

    @if (session('plotErrors'))
        <div class="card card-pad mb-6 ring-2 ring-red-200">
            <h2 class="section-title text-red-800">Fix these plot rows</h2>
            <ul class="mt-2 max-h-72 space-y-1 overflow-auto text-sm">
                @foreach (session('plotErrors') as $line => $errs)<li><strong>Row {{ $line }}:</strong> {{ implode('; ', (array) $errs) }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('ws.quick-launch.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3">
        @csrf
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <div class="card card-pad grid gap-4 sm:grid-cols-2">
                <h2 class="section-title sm:col-span-2">1. Layout & approval</h2>
                <x-field name="name" label="Layout name" required class="sm:col-span-2" placeholder="Sunrise Nagar" />
                <x-select name="approval_type" label="Approval type" :options="array_combine(\App\Models\Project::APPROVAL_TYPES, \App\Models\Project::APPROVAL_TYPES)" />
                <x-select name="approval_authority" label="Approval authority" :options="$authorities" placeholder="—" />
                <x-field name="approval_number" label="Approval / LP / RERA number" hint="Optional — shown as a trust badge when filled." />
                <x-field name="size_acres" type="number" step="0.0001" min="0.01" label="Total extent (acres)" required />
                <x-field name="survey_numbers" label="Survey number(s)" required />
                <x-field name="patta_numbers" label="Patta number(s)" required />
            </div>

            <div class="card card-pad grid gap-4 sm:grid-cols-2">
                <h2 class="section-title sm:col-span-2">2. Location</h2>
                <x-field name="village" label="Village" />
                <x-field name="location" label="Town / city" required />
                <x-field name="district" label="District" required />
                <x-field name="state" label="State" value="Tamil Nadu" required />
                <x-field name="pin" label="PIN" required inputmode="numeric" maxlength="6" />
                <x-field name="address" label="Address / road" />
                <x-field name="lat" label="Latitude" id="ql_lat" inputmode="decimal" />
                <x-field name="lng" label="Longitude" id="ql_lng" inputmode="decimal" />
                <div class="sm:col-span-2">
                    <p class="label">Map pin — tap the map to place the layout entrance</p>
                    <div class="h-64 rounded-xl ring-1 ring-navy-50" data-map-picker data-lat="ql_lat" data-lng="ql_lng" role="application" aria-label="Map: tap to set the layout location"></div>
                </div>
            </div>

            <div class="card card-pad space-y-4">
                <h2 class="section-title">3. Layout picture, photos & media</h2>
                <x-field name="layout" type="file" label="Approved layout picture (image or PDF, up to 20 MB)" accept="image/*,application/pdf" required />
                <div>
                    <label class="dropzone" for="ql_images"><x-icon name="upload" class="h-6 w-6" /><span><strong>Drop site photos here</strong> or tap to choose · {{ \App\Services\PropertyMedia::MIN_IMAGES }}–{{ $maxImages }} photos, JPG / PNG / WebP up to {{ \App\Services\PropertyMedia::maxKb() / 1024 }} MB each</span>
                        <input id="ql_images" type="file" name="images[]" multiple required accept="image/jpeg,image/png,image/webp" class="sr-only" data-dropzone></label>
                    @error('images')<p class="error">{{ $message }}</p>@enderror
                    @error('images.*')<p class="error">{{ $message }}</p>@enderror
                    <p class="hint">The first photo becomes the cover; you can reorder, caption and change the cover after launch.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="youtube_url" type="url" label="YouTube video link (optional)" placeholder="https://youtu.be/…" />
                    <x-field name="brochure" type="file" label="Brochure PDF (optional, max 10 MB)" accept="application/pdf" />
                </div>
            </div>

            <div class="card card-pad">@include('partials.property.features', ['features' => $features, 'selected' => [], 'highlights' => []])</div>

            <div class="card card-pad space-y-2">
                <div class="flex items-end justify-between gap-2">
                    <h2 class="section-title"><label for="ql_promo">5. Promotion text</label></h2>
                    <button type="button" class="btn-ghost btn-sm" data-ai-write="ql_promo" data-ai-kind="layout_promo" data-url="{{ route('ws.ai.write') }}"><x-icon name="sparkles" class="h-4 w-4" /> Write with AI</button>
                </div>
                <textarea id="ql_promo" name="promo_text" rows="6" class="input" required maxlength="5000">{{ old('promo_text') }}</textarea>
                @error('promo_text')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="card card-pad">@include('partials.property.contact', ['contact' => $contact])</div>

            <div class="card card-pad space-y-3">
                <h2 class="section-title">7. Plots</h2>
                <p class="text-sm text-muted">Same columns and checks as the normal launch import: <code>plot_no, patta_number, size_sqft, facing, rate_per_sqft, status</code> are required; offer, offer price and offer valid till (DD-MM-YYYY) are optional. Nothing is saved until every row is correct.</p>
                <x-field name="plots_file" type="file" label="CSV or Excel file" accept=".csv,.xlsx" />
                <x-textarea name="plots_csv" label="…or paste the CSV text" rows="6" placeholder="plot_no,patta_number,size_sqft,length_ft,width_ft,facing,…" />
            </div>
        </div>
        <div>
            <div class="card card-pad sticky top-20 space-y-3">
                <h2 class="section-title">Launch</h2>
                <ul class="space-y-1 text-sm text-muted">
                    <li>✓ Goes live on the marketplace straight away</li>
                    <li>✓ Labelled “Direct launch” in your project list</li>
                    <li>✓ Counts toward your plan's project limit</li>
                    <li>✓ Can be converted to a full project later to start stage tracking</li>
                </ul>
                <button class="btn-primary w-full"><x-icon name="rocket" class="h-4 w-4" /> Launch layout</button>
            </div>
        </div>
    </form>
</x-layouts.workspace>
