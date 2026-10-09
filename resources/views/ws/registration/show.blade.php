<x-layouts.workspace :title="'Registration — Plot '.$r->plot->plot_no">
    @php($sale = $r->sale->setRelation('plot', $r->plot)->setRelation('project', $r->project)->setRelation('customer', $r->customer))
    @php($tenant = app(\App\Support\Tenancy::class)->get())
    @php($canEdit = auth()->user()->hasPerm('registration.update'))
    <x-page-header :title="'Registration — Plot '.$r->plot->plot_no" :subtitle="$r->project->name.' · '.$r->customer->name.' · '.\App\Models\Registration::STATUSES[$r->status]" :back="route('ws.registrations.index')">
        <a href="{{ route('ws.registrations.pack', $r) }}" class="btn-light" target="_blank" rel="noopener"><x-icon name="printer" class="h-4 w-4" /> Registration pack</a>
        @if (in_array($r->status, ['ror_completed', 'sold']))<a href="{{ route('ws.registrations.ack', $r) }}" class="btn-light" target="_blank" rel="noopener"><x-icon name="printer" class="h-4 w-4" /> Acknowledgement</a>@endif
    </x-page-header>

    @php($steps = ['draft' => 'Prepare & print', 'ror_init' => 'With document writer', 'ror_completed' => 'Registered', 'sold' => 'Sold'])
    @php($idx = array_search($r->status, array_keys($steps)))
    <ol class="mb-6 grid grid-cols-2 gap-2 md:grid-cols-4">
        @foreach (array_values($steps) as $i => $l)
            <li class="rounded-lg px-3 py-2 text-sm font-semibold {{ $i < $idx ? 'bg-teal-50 text-teal-800' : ($i === $idx ? 'bg-navy text-white' : 'bg-white text-muted ring-1 ring-navy-50') }}">{{ $i + 1 }}. {{ $l }}</li>
        @endforeach
    </ol>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            @if ($r->status === 'draft' && $canEdit)
                <form method="POST" action="{{ route('ws.registrations.update', $r) }}" class="space-y-6">
                    @csrf @method('PUT')
                    @include('ws.registration.fields', ['sros' => $sros, 'sro' => $r->sro, 'tenant' => $tenant])
                    @foreach ($r->checklist ?? [] as $c)@if ($c['done'])<input type="hidden" name="checklist[]" value="{{ $c['code'] }}">@endif @endforeach
                    <button class="btn-primary">Save details</button>
                </form>
            @else
                <div class="card card-pad text-sm">
                    <h2 class="section-title">Details</h2>
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div><dt class="text-muted">Registration date</dt><dd class="font-semibold">@date($r->registration_date)</dd></div>
                        <div><dt class="text-muted">SRO</dt><dd class="font-semibold">{{ $r->sro?->name }}</dd></div>
                        @foreach ($r->parties as $p)<div><dt class="text-muted">{{ ucfirst($p->party_type) }}</dt><dd class="font-semibold">{{ $p->name }}<span class="block font-normal">{{ $p->address }} · PAN {{ $p->panFor($viewer) ?? '—' }}</span></dd></div>@endforeach
                        @foreach ($r->witnesses as $w)<div><dt class="text-muted">Witness</dt><dd>{{ $w->name }}, {{ $w->address }}</dd></div>@endforeach
                        @if ($r->registered_doc_no)<div><dt class="text-muted">Registered document</dt><dd class="font-semibold">{{ $r->registered_doc_no }} · @date($r->registered_doc_date)</dd></div>@endif
                        @if ($r->physical_file_no)<div><dt class="text-muted">Physical file no.</dt><dd class="font-semibold">{{ $r->physical_file_no }}</dd></div>@endif
                    </dl>
                </div>
            @endif
        </div>
        <div class="space-y-6">
            <form method="POST" action="{{ route('ws.registrations.update', $r) }}" class="card card-pad">
                @csrf @method('PUT') <input type="hidden" name="checklist_only" value="1">
                <h2 class="section-title">Checklist</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($r->checklist ?? [] as $c)<x-checkbox name="checklist[]" :value="$c['code']" :label="$c['code'].' · '.$c['name'].($c['mandatory'] ? ' *' : '')" :checked="$c['done']" :disabled="! $canEdit || $r->status !== 'draft'" />@endforeach
                </div>
                @if ($canEdit && $r->status === 'draft')<button class="btn-light btn-sm mt-3">Save checklist</button>@endif
            </form>
            <div class="card card-pad">
                <h2 class="section-title">Documents</h2>
                <ul class="mt-3 space-y-1 text-sm">@forelse ($r->files as $f)<li><a href="{{ route('files.show', $f) }}" target="_blank" rel="noopener">{{ $f->original_name }}</a> <span class="text-xs text-muted">{{ $f->sizeLabel() }}</span></li>@empty<li class="text-muted">No documents attached.</li>@endforelse</ul>
                @if ($canEdit && $r->status !== 'sold')
                    <form method="POST" action="{{ route('ws.registrations.upload', $r) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2">@csrf
                        <x-field name="file" type="file" label="Attach document" accept=".pdf,.jpg,.jpeg,.png,.webp,.docx" required class="flex-1" />
                        <button class="btn-light btn-sm">Attach</button>
                    </form>
                    <p class="mt-2 text-xs text-muted">Attached documents are shared with the buyer in their account.</p>
                @endif
            </div>
            @if ($canEdit)
                @if ($r->status === 'draft')
                    <div class="card card-pad"><h2 class="section-title">Submit</h2><p class="mt-1 text-sm text-muted">Send to the document writer once the pack is printed and documents are attached.</p>
                        <x-confirm :action="route('ws.registrations.submit', $r)" message="Submit to the document writer? The plot becomes ROR-Init." class="btn-primary mt-3 w-full">Submit (ROR-Init)</x-confirm></div>
                @elseif ($r->status === 'ror_init')
                    <form method="POST" action="{{ route('ws.registrations.complete', $r) }}" class="card card-pad space-y-3">@csrf
                        <h2 class="section-title">After registration</h2>
                        <x-field name="registered_doc_no" label="Registered document no." required />
                        <x-field name="registered_doc_date" type="date" label="Registered on" required :value="today()->toDateString()" />
                        <button class="btn-primary w-full">Mark ROR-Completed</button>
                    </form>
                @elseif ($r->status === 'ror_completed')
                    <form method="POST" action="{{ route('ws.registrations.sold', $r) }}" class="card card-pad space-y-3">@csrf
                        <h2 class="section-title">Close the file</h2>
                        <p class="text-sm text-muted">Verify the acknowledgement was signed, then record the physical file number.</p>
                        <x-field name="physical_file_no" label="Physical file no." required />
                        <button class="btn-teal w-full">Mark Sold</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-layouts.workspace>
