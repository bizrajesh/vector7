{{-- Media manager for a saved listing or layout (spec 17.3). Vars: $owner (Listing|Project), $kind ('listing'|'project'), $canEdit --}}
@php($images = $owner->images()->with('file')->get())
@php($max = \App\Services\PropertyMedia::maxFor($owner))
<div class="card card-pad space-y-4" id="media">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="section-title">Photos ({{ $images->count() }} / {{ $max }})</h2>
            <p class="text-sm text-muted">At least {{ \App\Services\PropertyMedia::MIN_IMAGES }} photos. Drag to reorder, pick a cover and add captions. Photos are resized, converted to WebP and stripped of location data{{ $owner->tenantRel?->setting()->image_watermark ? ', with a small vector7 watermark' : '' }}.</p>
        </div>
        @if ($canEdit && $images->isNotEmpty() && \App\Services\ClaudeClient::available())
            <form method="POST" action="{{ route('ws.media.ai-check', [$kind, $owner->id]) }}">@csrf<button class="btn-light btn-sm"><x-icon name="sparkles" class="h-4 w-4" /> Check photos with AI</button></form>
        @endif
    </div>
    @if ($images->isNotEmpty())
        <ol class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-sortable="{{ $canEdit ? route('ws.media.order', [$kind, $owner->id]) : '' }}">
            @foreach ($images as $img)
                <li class="overflow-hidden rounded-xl bg-page ring-1 ring-navy-50 {{ $img->is_cover ? 'ring-2 ring-teal' : '' }}" data-id="{{ $img->id }}" @if ($canEdit) draggable="true" @endif>
                    <img src="{{ $img->url(true) }}" alt="{{ $img->caption ?: 'Photo '.$loop->iteration }}" class="aspect-[4/3] w-full object-cover" loading="lazy" width="400" height="300">
                    <div class="space-y-1 p-2 text-xs">
                        @if ($img->is_cover)<span class="badge-teal">Cover</span>@endif
                        @if ($img->ai_warning)<p class="font-semibold text-amber-800"><x-icon name="alert" class="inline h-3 w-3" /> {{ $img->ai_warning }}</p>@endif
                        @if ($canEdit)
                            <form method="POST" action="{{ route('ws.media.caption', [$kind, $owner->id, $img->id]) }}" class="flex gap-1">@csrf @method('PUT')
                                <input name="caption" value="{{ $img->caption }}" maxlength="150" class="input py-1 text-xs" placeholder="Caption" aria-label="Caption for photo {{ $loop->iteration }}">
                                <button class="btn-ghost btn-sm px-2" aria-label="Save caption">✓</button>
                            </form>
                            <div class="flex flex-wrap gap-1">
                                @unless ($img->is_cover)<form method="POST" action="{{ route('ws.media.cover', [$kind, $owner->id, $img->id]) }}">@csrf<button class="btn-ghost btn-sm px-2">Make cover</button></form>@endunless
                                <form method="POST" action="{{ route('ws.media.order', [$kind, $owner->id]) }}">@csrf<input type="hidden" name="move" value="{{ $img->id }}:up"><button class="btn-ghost btn-sm px-2" aria-label="Move earlier" @disabled($loop->first)>←</button></form>
                                <form method="POST" action="{{ route('ws.media.order', [$kind, $owner->id]) }}">@csrf<input type="hidden" name="move" value="{{ $img->id }}:down"><button class="btn-ghost btn-sm px-2" aria-label="Move later" @disabled($loop->last)>→</button></form>
                                <x-confirm :action="route('ws.media.destroy', [$kind, $owner->id, $img->id])" method="DELETE" message="Delete this photo?" class="btn-ghost btn-sm px-2 text-red-700">Delete</x-confirm>
                            </div>
                        @elseif ($img->caption)
                            <p>{{ $img->caption }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
    @if ($canEdit && $images->count() < $max)
        <form method="POST" action="{{ route('ws.media.upload', [$kind, $owner->id]) }}" enctype="multipart/form-data" class="space-y-2">@csrf
            <label class="dropzone" for="media_files_{{ $kind }}">
                <x-icon name="upload" class="h-6 w-6" />
                <span><strong>Drop photos here</strong> or tap to choose · JPG, PNG or WebP, up to 5 MB each</span>
                <input id="media_files_{{ $kind }}" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-dropzone>
            </label>
            @error('images')<p class="error">{{ $message }}</p>@enderror
            @error('images.*')<p class="error">{{ $message }}</p>@enderror
            <button class="btn-primary btn-sm">Upload photos</button>
        </form>
    @endif

    <div class="grid gap-4 border-t border-navy-50 pt-4 sm:grid-cols-2">
        <div>
            <h3 class="font-bold">Brochure (PDF)</h3>
            @if ($owner->brochure)
                <p class="text-sm"><a href="{{ route('files.show', $owner->brochure) }}" target="_blank" rel="noopener">{{ $owner->brochure->original_name }}</a> · {{ \App\Support\Format::bytes($owner->brochure->size) }}</p>
                @if ($canEdit)<x-confirm :action="route('ws.media.brochure.destroy', [$kind, $owner->id])" method="DELETE" message="Remove the brochure?" class="btn-ghost btn-sm text-red-700">Remove</x-confirm>@endif
            @elseif (! $canEdit)<p class="text-sm text-muted">None</p>@endif
            @if ($canEdit)
                <form method="POST" action="{{ route('ws.media.brochure', [$kind, $owner->id]) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-end gap-2">@csrf
                    <x-field name="brochure" type="file" label="{{ $owner->brochure ? 'Replace' : 'Upload' }} (max 10 MB)" accept="application/pdf" required />
                    <button class="btn-light btn-sm">Upload</button>
                </form>
            @endif
        </div>
        <div>
            <h3 class="font-bold">YouTube video</h3>
            @if ($owner->youtube_url)<p class="text-sm"><a href="{{ $owner->youtube_url }}" target="_blank" rel="noopener">{{ $owner->youtube_url }}</a></p>@elseif (! $canEdit)<p class="text-sm text-muted">None</p>@endif
            @if ($canEdit)
                <form method="POST" action="{{ route('ws.media.youtube', [$kind, $owner->id]) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf @method('PUT')
                    <x-field name="youtube_url" type="url" label="Video link" :value="$owner->youtube_url" placeholder="https://youtu.be/…" />
                    <button class="btn-light btn-sm">Save</button>
                </form>
            @endif
        </div>
    </div>
</div>
