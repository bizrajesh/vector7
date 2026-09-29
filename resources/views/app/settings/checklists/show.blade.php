<x-layouts.app :title="$template->name">
    <x-page-header :title="$template->name" :subtitle="ucfirst($template->purpose).' checklist'" :back="route('app.settings.checklists.index')" />
    <div class="grid gap-4 lg:grid-cols-3">
        <ol class="card lg:col-span-2">
            @foreach ($template->items as $item)
                <li class="flex items-center gap-3 border-t border-line-soft px-4 py-3 first:border-t-0">
                    <span class="num w-6 text-xs font-bold text-ink-muted">{{ $loop->iteration }}</span>
                    <span class="flex-1 text-sm font-semibold">{{ $item->label }}</span>
                    @if ($item->needs_upload)<span class="badge-os">Upload</span>@endif
                    @if ($item->needs_date)<span class="badge-ror">Date</span>@endif
                    @if ($item->is_required)<span class="badge-av">Required</span>@endif
                    <form method="POST" action="{{ route('app.settings.checklists.items.destroy', [$template, $item]) }}" data-confirm="Remove this item?">@csrf @method('DELETE')
                        <button class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-700" aria-label="Remove {{ $item->label }}"><x-icon name="trash" class="h-4 w-4" /></button>
                    </form>
                </li>
            @endforeach
        </ol>
        <form method="POST" action="{{ route('app.settings.checklists.items.store', $template) }}" class="card-pad flex flex-col gap-3">
            @csrf
            <h2 class="section-title">Add item</h2>
            <x-field name="label" label="Item" required />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" value="1" class="check" checked> Required</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="needs_upload" value="1" class="check"> Needs a file upload</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="needs_date" value="1" class="check"> Needs a date</label>
            <button type="submit" class="btn-primary">Add item</button>
        </form>
    </div>
</x-layouts.app>
