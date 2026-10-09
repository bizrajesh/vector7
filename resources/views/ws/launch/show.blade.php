<x-layouts.workspace :title="'Launch — '.$project->name">
    @php($layoutUrl = $project->layout_file_id ? route('files.show', $project->layout_file_id) : null)
    <x-page-header :title="$project->name" :subtitle="$project->project_code.' · '.$project->location.' · '.$plots->count().' plots'" :back="route('ws.launch.index')">
        @if ($project->status === 'launched')<a href="{{ $project->publicUrl() }}" class="btn-light" target="_blank" rel="noopener"><x-icon name="globe" class="h-4 w-4" /> Public page</a>@endif
        @can('launch.export')<a href="{{ route('ws.plots.export', $project) }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Export plots</a>@endcan
        @if ($canGoLive && $project->status === 'ready_to_launch')
            <x-confirm :action="route('ws.launch.go-live', $project)" message="Publish {{ $project->name }} and its plots on the marketplace?" class="btn-teal"><x-icon name="rocket" class="h-4 w-4" /> Go live</x-confirm>
        @endif
    </x-page-header>

    @unless ($canEdit)
        <div class="flash-warn mb-4">You have read-only access to launch screens. Only Tenant Admins and Managers can launch, import or edit plots, prices and offers.</div>
    @endunless

    @if ($project->status === 'ready_to_launch')
        <ol class="mb-6 grid gap-3 sm:grid-cols-4">
            @foreach ([['Layout picture', (bool) $project->layout_file_id], ['Promotion text', filled($project->promo_text)], ['Plots loaded', $plots->isNotEmpty()], ['Go live', false]] as $i => [$label, $done])
                <li class="card flex items-center gap-3 p-4"><span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $done ? 'bg-teal-700 text-white' : 'bg-page text-muted' }}">{!! $done ? \App\Support\Icons::svg('check', 'h-4 w-4') : $i + 1 !!}</span><span class="text-sm font-semibold">{{ $label }}</span></li>
            @endforeach
        </ol>
    @endif

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="space-y-6 xl:col-span-3">
            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="section-title">Layout & plot map</h2>
                    <div class="flex flex-wrap gap-2 text-xs">@foreach ($stats as $k => $n)<x-status :status="$k" :label="\App\Models\Plot::STATUSES[$k].': '.$n" />@endforeach</div>
                </div>
                <div class="mt-4">
                    @if ($layoutUrl || $plots->isNotEmpty())
                        @include('partials.plot-map', ['plots' => $plots, 'layoutUrl' => $layoutUrl])
                    @else
                        <x-empty title="No layout uploaded yet" icon="map" />
                    @endif
                </div>
                @if ($canEdit)
                    <form method="POST" action="{{ route('ws.launch.layout', $project) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3 border-t border-navy-50 pt-4">
                        @csrf
                        <x-field name="layout" type="file" label="{{ $layoutUrl ? 'Replace layout picture' : 'Upload layout picture' }}" accept=".jpg,.jpeg,.png,.webp,.pdf" required hint="JPG, PNG, WebP or PDF, max 20 MB" class="flex-1" />
                        <button class="btn-primary"><x-icon name="upload" class="h-4 w-4" /> Upload</button>
                    </form>
                    @if ($layoutUrl && $project->layoutFile && str_starts_with($project->layoutFile->mime, 'image/') && $plots->isNotEmpty())
                        <details class="mt-4 border-t border-navy-50 pt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-teal-700">Place plots on the layout picture</summary>
                            <p class="mt-2 text-sm text-muted">Choose a plot number, then click its position on the picture. The next plot is selected automatically. (DXF imports draw outlines automatically.)</p>
                            <select class="input mt-2 max-w-xs" data-pin-plot aria-label="Plot to place">
                                @foreach ($plots as $p)<option value="{{ $p->id }}" data-url="{{ route('ws.plots.pin', [$project, $p]) }}">{{ $p->plot_no }} {{ $p->map_x !== null ? '✓' : '' }}</option>@endforeach
                            </select>
                            <div class="relative mt-3 cursor-crosshair select-none overflow-hidden rounded-xl ring-1 ring-navy-50" data-pin-editor>
                                <img src="{{ $layoutUrl }}" alt="Layout plan — click to place the selected plot" class="block h-auto w-full" draggable="false">
                                <div class="pointer-events-none absolute inset-0" data-pin-layer>
                                    @foreach ($plots->whereNotNull('map_x') as $p)
                                        <span class="absolute -translate-x-1/2 -translate-y-1/2 rounded-full bg-navy px-1.5 py-0.5 text-[10px] font-bold text-white" data-pin="{{ $p->id }}" data-left="{{ (float) $p->map_x }}" data-top="{{ (float) $p->map_y }}">{{ $p->plot_no }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    @endif
                @endif
            </div>

            <div class="card">
                <div class="flex flex-wrap items-center justify-between gap-2 p-4">
                    <h2 class="section-title">Plots ({{ $plots->count() }})</h2>
                    <p class="text-sm text-muted">Total {{ \App\Support\Format::num($plots->sum('size_sqft')) }} sq ft @if ($project->estimate) · sellable estimate {{ \App\Support\Format::num($project->estimate->sellable_sqft) }} sq ft @endif</p>
                </div>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Plot</th><th>Patta</th><th class="num">Sq ft</th><th>Facing</th><th class="num">Rate / sq ft</th><th class="num">Price</th><th>Offer</th><th>Status</th>@if ($canEdit)<th></th>@endif</tr></thead>
                    <tbody>
                    @forelse ($plots as $p)
                        <tr>
                            <td class="font-bold" data-plot='@json($p->tooltipData())'>{{ $p->plot_no }} @if ($p->is_corner)<span class="badge-gray">Corner</span>@endif</td>
                            <td class="text-sm">{{ $p->patta_number }}</td>
                            <td class="num">{{ \App\Support\Format::num($p->size_sqft) }}</td>
                            <td class="text-sm">{{ $p->facing }}</td>
                            <td class="num">@inr($p->rate_per_sqft)</td>
                            <td class="num">@inr($p->actualPrice())</td>
                            <td class="text-xs">@if ($p->offerIsActive())<span class="font-semibold text-teal-700">@inr($p->offerPrice())</span><br>till @date($p->offer_valid_till)@elseif ($p->offer_rate_per_sqft)<span class="text-muted">expired</span>@else — @endif</td>
                            <td><x-status :status="$p->status" /></td>
                            @if ($canEdit)
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('ws.plots.edit', [$project, $p]) }}" class="btn-light btn-sm">Edit</a>
                                    @if ($canDelete && in_array($p->status, ['available', 'blocked']))<x-confirm :action="route('ws.plots.destroy', [$project, $p])" method="DELETE" message="Delete plot {{ $p->plot_no }}?" class="btn-ghost btn-sm text-red-700">Delete</x-confirm>@endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty title="No plots yet" icon="grid">Load plots from a CSV/Excel file or extract them from the layout drawing.</x-empty></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="min-w-0 space-y-6 xl:col-span-2">
            @if ($canImport)
                <div class="card card-pad">
                    <h2 class="section-title">Load plots</h2>
                    <p class="mt-1 text-sm text-muted">Header names must match the sample. Re-importing matches on <code>plot_no</code> and updates those plots. Nothing is imported until every error is fixed.</p>
                    <a href="{{ route('ws.launch.sample') }}" class="mt-2 inline-flex text-sm font-semibold"><x-icon name="download" class="mr-1 h-4 w-4" /> Download sample (3 rows)</a>
                    <form method="POST" action="{{ route('ws.launch.import', $project) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <x-field name="file" type="file" label="CSV or Excel file" accept=".csv,.xlsx" />
                        <x-textarea name="csv" label="…or paste CSV text" rows="4" placeholder="plot_no,patta_number,size_sqft,length_ft,width_ft,facing,…" />
                        <button class="btn-primary w-full">Check & preview</button>
                    </form>
                    <form method="POST" action="{{ route('ws.launch.extract', $project) }}" enctype="multipart/form-data" class="mt-6 space-y-3 border-t border-navy-50 pt-4">
                        @csrf
                        <p class="flex items-center gap-2 font-semibold"><x-icon name="sparkles" class="h-5 w-5 text-teal-700" /> Extract from the approved layout</p>
                        <p class="text-xs text-muted">DXF: plot areas are measured from closed polylines (no AI needed). PDF: text is read and Claude turns it into plots. Photo: cleaned up, then read by Claude vision. {{ $aiReady ? '' : 'AI is not configured yet — DXF still works.' }}</p>
                        <x-field name="source" type="file" label="DXF, PDF or photo" accept=".dxf,.pdf,.jpg,.jpeg,.png,.webp" required />
                        <button class="btn-light w-full">Extract plots</button>
                    </form>
                </div>
            @endif

            <div class="card card-pad">
                <h2 class="section-title">Promotion</h2>
                @if ($canEdit)
                    <form method="POST" action="{{ route('ws.launch.details', $project) }}" class="mt-3 space-y-3">
                        @csrf
                        <x-textarea name="promo_text" label="Promotion text / description" :value="$project->promo_text" rows="7" />
                        <x-textarea name="facilities_text" label="Facilities (one per line)" :value="implode(PHP_EOL, $project->facilities ?? [])" rows="4" placeholder="BT roads&#10;Street lights&#10;Children's park" />
                        <x-field name="map_url" type="url" label="Location map link" :value="$project->map_url" />
                        <input type="hidden" name="is_featured" value="0"><x-checkbox name="is_featured" label="Feature in the home-page carousel" :checked="$project->is_featured" />
                        <button class="btn-primary">Save promotion</button>
                    </form>
                    <form method="POST" action="{{ route('ws.launch.ai-text', $project) }}" class="mt-2">@csrf<button class="btn-ghost btn-sm" @disabled(! $aiReady)><x-icon name="sparkles" class="h-4 w-4" /> Write with AI</button></form>
                @else
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $project->promo_text ?: 'No promotion text yet.' }}</p>
                    <p class="mt-2 text-sm text-muted">{{ implode(' · ', $project->facilities ?? []) }}</p>
                @endif
            </div>

            <div class="card card-pad">
                <h2 class="section-title">Gallery</h2>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach ($gallery as $img)
                        <div class="relative">
                            <img src="{{ route('files.show', $img) }}" alt="{{ $img->original_name }}" class="aspect-square w-full rounded-lg object-cover" loading="lazy">
                            @if ($canEdit)<form method="POST" action="{{ route('ws.launch.gallery.remove', [$project, $img]) }}" class="absolute right-1 top-1" data-confirm="Remove this photo?">@csrf @method('DELETE')<button class="rounded-full bg-white/90 p-1 text-red-700 shadow" aria-label="Remove photo"><x-icon name="x" class="h-3 w-3" /></button></form>@endif
                        </div>
                    @endforeach
                </div>
                @if ($canEdit)
                    <form method="POST" action="{{ route('ws.launch.gallery', $project) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <x-field name="images[]" type="file" label="Add photos" accept=".jpg,.jpeg,.png,.webp" multiple required class="flex-1" />
                        <button class="btn-light">Upload</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.workspace>
