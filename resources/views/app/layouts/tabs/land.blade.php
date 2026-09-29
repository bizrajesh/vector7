<div class="grid gap-4 lg:grid-cols-2">
    <section class="card-pad">
        <h2 class="section-title mb-2">Land owners</h2>
        @forelse ($layout->owners as $owner)
            <div class="flex items-center gap-3 border-t border-line-soft py-2.5 text-sm first:border-t-0">
                <span class="flex-1"><span class="block font-semibold">{{ $owner->name }}</span><span class="text-xs text-ink-muted">{{ $owner->father_name ? 'S/o '.$owner->father_name.' · ' : '' }}{{ $owner->phone }} {{ $owner->share_pct ? '· '.(float) $owner->share_pct.'%' : '' }} · Aadhaar {{ $owner->aadhaar ? 'XXXX '.substr(preg_replace('/\D/', '', $owner->aadhaar), -4) : '—' }}</span></span>
                @if ($editable && $canManage)
                    <form method="POST" action="{{ route('app.layouts.owners.destroy', [$layout, $owner]) }}" data-confirm="Remove this owner?">@csrf @method('DELETE')<button class="rounded-lg p-2 text-ink-muted hover:text-red-700" aria-label="Remove owner"><x-icon name="trash" class="h-4 w-4" /></button></form>
                @endif
            </div>
        @empty
            <p class="text-sm text-ink-muted">No owners added.</p>
        @endforelse
        @if ($editable && $canManage)
            <form method="POST" action="{{ route('app.layouts.owners.store', $layout) }}" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">@csrf
                <x-field name="name" label="Owner name" required />
                <x-field name="father_name" label="Father's name" />
                <x-field name="phone" type="tel" label="Phone" />
                <x-field name="share_pct" type="number" step="0.01" label="Share of land %" />
                <x-field name="aadhaar" label="Aadhaar" inputmode="numeric" autocomplete="off" help="Stored encrypted" />
                <x-field name="pan" label="PAN" autocapitalize="characters" autocomplete="off" />
                <x-field name="address" label="Address" class="sm:col-span-2" />
                <div class="sm:col-span-2"><button class="btn-outline btn-sm">Add owner</button></div>
            </form>
        @endif
    </section>

    <section class="card-pad">
        <h2 class="section-title mb-2">Survey numbers</h2>
        @forelse ($layout->surveyNumbers as $survey)
            <div class="flex items-center gap-3 border-t border-line-soft py-2.5 text-sm first:border-t-0">
                <span class="flex-1"><span class="block font-semibold">S.No {{ $survey->survey_no }}{{ $survey->sub_division ? '/'.$survey->sub_division : '' }}</span><span class="num text-xs text-ink-muted">{{ number_format((float) $survey->extent_sqft) }} ft² · guideline @inr($survey->guideline_value_sqft)/ft² · value @inr($survey->extent_sqft * $survey->guideline_value_sqft)</span></span>
                @if ($editable && $canManage)
                    <form method="POST" action="{{ route('app.layouts.surveys.destroy', [$layout, $survey]) }}">@csrf @method('DELETE')<button class="rounded-lg p-2 text-ink-muted hover:text-red-700" aria-label="Remove survey number"><x-icon name="trash" class="h-4 w-4" /></button></form>
                @endif
            </div>
        @empty
            <p class="text-sm text-ink-muted">No survey numbers added.</p>
        @endforelse
        <p class="mt-2 text-sm font-semibold">Total guideline value: @inr($layout->surveyNumbers->sum(fn ($s) => $s->extent_sqft * $s->guideline_value_sqft))</p>
        @if ($editable && $canManage)
            <form method="POST" action="{{ route('app.layouts.surveys.store', $layout) }}" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2">@csrf
                <x-field name="survey_no" label="Survey no" required />
                <x-field name="sub_division" label="Sub-division" />
                <x-field name="extent_sqft" type="number" step="0.01" label="Extent (sqft)" required />
                <x-field name="guideline_value_sqft" type="number" step="0.01" label="Guideline value (₹/sqft)" required />
                <div class="sm:col-span-2"><button class="btn-outline btn-sm">Add survey number</button></div>
            </form>
        @endif
    </section>

    <section class="card-pad lg:col-span-2">
        <h2 class="section-title mb-2">Documents</h2>
        <div class="overflow-x-auto">
            <table class="table min-w-[560px]">
                <thead><tr><th>Type</th><th>Number</th><th>Date</th><th>Status</th><th>File</th><th></th></tr></thead>
                <tbody>
                @forelse ($layout->documents as $doc)
                    <tr>
                        <td class="font-semibold">{{ $doc->doc_type }}</td><td>{{ $doc->doc_no ?? '—' }}</td><td>{{ $doc->doc_date?->format('j M Y') ?? '—' }}</td>
                        <td><span class="{{ $doc->status === 'obtained' ? 'badge-av' : 'badge-bk' }}">{{ ucfirst($doc->status) }}</span></td>
                        <td>@if ($doc->file_path && $canManage)<a href="{{ route('app.layouts.documents.download', [$layout, $doc]) }}" class="inline-flex items-center gap-1 text-sm font-semibold"><x-icon name="download" class="h-4 w-4" />Download</a>@else — @endif</td>
                        <td class="text-right">@if ($editable && $canManage)<form method="POST" action="{{ route('app.layouts.documents.destroy', [$layout, $doc]) }}" data-confirm="Delete this document?">@csrf @method('DELETE')<button class="rounded-lg p-2 text-ink-muted hover:text-red-700" aria-label="Delete document"><x-icon name="trash" class="h-4 w-4" /></button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-ink-muted">No documents yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($canManage)
            <form method="POST" action="{{ route('app.layouts.documents.store', $layout) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-3 lg:grid-cols-6">@csrf
                <x-select name="doc_type" label="Type" :options="array_combine($t = ['Patta', 'Chitta', 'EC', 'Parent document', 'FMB sketch', 'Owner ID', 'Approval', 'Other'], $t)" />
                <x-field name="doc_no" label="Number" />
                <x-field name="doc_date" type="date" label="Date" />
                <x-select name="status" label="Status" :options="['obtained' => 'Obtained', 'pending' => 'Pending']" />
                <div class="sm:col-span-2"><label for="doc-file" class="label">File (PDF/JPG/PNG, max 10 MB)</label><input id="doc-file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="input py-2.5"></div>
                <div class="sm:col-span-3 lg:col-span-6"><button class="btn-outline btn-sm">Save document</button></div>
            </form>
        @endif
    </section>
</div>
