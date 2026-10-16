{{-- Features / amenities picker (spec 17.4). Vars: $features (grouped by group), $selected (ids), $highlights (strings) --}}
@php($selected = collect(old('features', $selected ?? []))->map(fn ($v) => (int) $v)->all())
@php($highlights = old('highlights', $highlights ?? []))
<fieldset class="space-y-4">
    <legend class="section-title">Features & amenities</legend>
    @foreach ($features as $group => $items)
        <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-muted">{{ $group }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($items as $f)
                    <label class="chip-check">
                        <input type="checkbox" name="features[]" value="{{ $f->id }}" class="sr-only peer" @checked(in_array($f->id, $selected, true))>
                        <span class="chip-check-face"><x-icon :name="$f->icon" class="h-4 w-4" /> {{ $f->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
    <div>
        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-muted">Your own highlights (up to {{ \App\Services\PropertyDetails::MAX_CUSTOM }})</p>
        <div class="grid gap-2 sm:grid-cols-2">
            @for ($i = 0; $i < \App\Services\PropertyDetails::MAX_CUSTOM; $i++)
                <input name="highlights[]" value="{{ $highlights[$i] ?? '' }}" maxlength="80" class="input {{ $i >= max(3, count(array_filter($highlights)) + 1) ? 'hidden sm:block' : '' }}" placeholder="{{ ['e.g. 5 min to Medical College', 'e.g. Wide 40 ft main road', 'e.g. Ready for construction'][$i] ?? 'Highlight' }}" aria-label="Highlight {{ $i + 1 }}">
            @endfor
        </div>
        @error('highlights')<p class="error">{{ $message }}</p>@enderror
    </div>
</fieldset>
