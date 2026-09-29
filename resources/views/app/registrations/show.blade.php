@php
    $ongoing = $registration->status === 'ongoing';
    $done = $registration->items->where('is_done', true)->count();
    $total = $registration->items->count();
@endphp
<x-layouts.app :title="'Registration · '.$registration->plot->plot_no">
    <x-page-header :title="'Registration · Plot '.$registration->plot->plot_no" :subtitle="$registration->sale->customer->name.' · '.$registration->plot->layout->name" :back="route('app.plots.show', $registration->plot)">
        <x-slot:actions>
            <x-status :status="$registration->plot->status" />
            <a href="{{ route('app.registrations.details', $registration) }}" class="btn-ghost btn-sm"><x-icon name="print" class="h-4 w-4" />Details for document writer</a>
            @unless ($ongoing)
                <a href="{{ route('app.registrations.ack', $registration) }}" class="btn-primary btn-sm"><x-icon name="print" class="h-4 w-4" />Acknowledgement</a>
            @endunless
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="card-pad lg:col-span-2">
            <div class="flex items-baseline justify-between"><h2 class="section-title">Checklist</h2><span class="num text-sm text-ink-muted">{{ $done }} / {{ $total }} done</span></div>
            <div class="progress mt-2"><span style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span></div>
            <div class="mt-3 flex flex-col">
                @foreach ($registration->items as $item)
                    <form method="POST" action="{{ route('app.registrations.items.update', [$registration, $item]) }}" enctype="multipart/form-data" class="grid gap-2 border-t border-line-soft py-3 first:border-t-0 sm:grid-cols-[1fr_auto]">
                        @csrf @method('PUT')
                        <div class="flex flex-col gap-2">
                            <p class="flex items-center gap-2 text-sm font-semibold">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md {{ $item->is_done ? 'bg-teal text-white' : 'border border-[#D6D2BD]' }}">@if ($item->is_done)<x-icon name="check" class="h-4 w-4" stroke="3" />@endif</span>
                                {{ $item->label }} @if ($item->is_required)<span class="text-red-700" aria-label="required">*</span>@endif
                                @if ($item->file_path)<a href="{{ route('app.registrations.items.file', [$registration, $item]) }}" class="ml-auto text-xs font-semibold">View file</a>@endif
                            </p>
                            @if ($ongoing)
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <input name="value" value="{{ $item->value }}" class="input min-h-[40px] text-sm" placeholder="Details / number" aria-label="{{ $item->label }} details">
                                    @if ($item->needs_date)<input type="date" name="date_value" value="{{ $item->date_value?->toDateString() }}" class="input min-h-[40px] text-sm" aria-label="{{ $item->label }} date">@endif
                                    @if ($item->needs_upload)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp" {{ str_contains(strtolower($item->label), 'photo') ? 'capture=environment' : '' }} class="input min-h-[40px] py-2 text-sm" aria-label="Upload {{ $item->label }}">@endif
                                </div>
                            @else
                                <p class="text-sm text-ink-muted">{{ $item->value }} {{ $item->date_value?->format('j M Y') }}</p>
                            @endif
                        </div>
                        @if ($ongoing)
                            <div class="flex items-center gap-2 sm:flex-col sm:items-stretch">
                                <input type="hidden" name="is_done" value="0">
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_done" value="1" class="check" @checked($item->is_done)> Verified</label>
                                <button class="btn-ghost btn-sm">Save</button>
                            </div>
                        @endif
                    </form>
                @endforeach
            </div>
        </section>

        <section class="flex flex-col gap-4">
            <div class="card-pad text-sm">
                <h2 class="section-title mb-2">Details</h2>
                <p><span class="text-ink-muted">Document writer:</span> {{ $registration->writer?->name ?? $registration->document_writer_name ?? '—' }}</p>
                <p><span class="text-ink-muted">Sub-Registrar office:</span> {{ $registration->office?->name ?? '—' }}</p>
                <p><span class="text-ink-muted">Planned date:</span> {{ $registration->planned_date?->format('j M Y') ?? '—' }}</p>
                @unless ($ongoing)
                    <p class="mt-2"><span class="text-ink-muted">Registered:</span> {{ $registration->registration_date->format('j M Y') }} · Doc {{ $registration->document_no }}</p>
                    @if ($registration->deed_path)<a href="{{ route('app.registrations.deed', $registration) }}" class="mt-2 inline-flex items-center gap-1 font-semibold"><x-icon name="download" class="h-4 w-4" />Registered deed</a>@endif
                @endunless
            </div>
            @if ($ongoing)
                <form method="POST" action="{{ route('app.registrations.complete', $registration) }}" enctype="multipart/form-data" class="card-pad flex flex-col gap-3" data-confirm="Complete registration and mark the plot as Sold?">@csrf
                    <h2 class="section-title">Complete registration</h2>
                    <x-field name="registration_date" type="date" label="Registration date" :value="now()->toDateString()" required />
                    <x-field name="document_no" label="Document number" required />
                    <x-select name="sub_registrar_office_id" label="Sub-Registrar office" :options="$offices->pluck('name', 'id')" :value="$registration->sub_registrar_office_id" placeholder="Choose office" required />
                    <div><label for="deed" class="label">Registered deed (PDF/JPG)</label><input id="deed" type="file" name="deed" accept=".pdf,.jpg,.jpeg,.png" class="input py-2.5"></div>
                    <button class="btn-primary">Mark as Sold</button>
                    <p class="help">All required checklist items must be verified and there must be no dues.</p>
                </form>
            @endif
        </section>
    </div>
</x-layouts.app>
