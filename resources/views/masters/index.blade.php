@php
    $r = fn ($name, ...$p) => route($scope.'.masters.'.$name, ...$p);
    $title = $scope === 'app' ? 'Prerequisites' : 'Masters';
@endphp
<x-layouts.workspace :title="$title">
    <x-page-header :title="$title" :subtitle="$scope === 'app' ? 'App-level templates shared read-only with every tenant. Tenants copy them and edit their own copy.' : 'Your own copies of the masters. They drive estimates, approval tracking and document checklists.'">
        @if ($scope === 'app')
            @can('prerequisites.export')<a href="{{ route('app.masters.export') }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Export template</a>@endcan
        @else
            <a href="{{ route('ws.template.download') }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Blank template</a>
            @can('masters.export')<a href="{{ route('ws.template.export') }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Export my setup</a>@endcan
        @endif
    </x-page-header>
    @include('partials.import-errors')

    @if ($canCreate)
        <details class="card card-pad mb-6">
            <summary class="cursor-pointer font-bold">Import the pre-configuration template</summary>
            <form method="POST" action="{{ $scope === 'app' ? route('app.masters.import') : route('ws.masters.import') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-field name="file" type="file" label="Template (.xlsx)" accept=".xlsx" required class="flex-1" />
                <button class="btn-primary"><x-icon name="upload" class="h-4 w-4" /> Validate & replace masters</button>
            </form>
            <p class="mt-2 text-xs text-muted">Every sheet is validated first (unique IDs, valid dependencies with no loops, document IDs that exist, costs &gt; 0…). Nothing changes if any error is found.</p>
            @if ($scope === 'ws')
                <div class="mt-4 border-t border-navy-50 pt-4"><x-confirm :action="route('ws.setup.defaults')" message="Replace your masters with the vector7 App defaults?" class="btn-light btn-sm">Reset to App defaults</x-confirm></div>
            @endif
        </details>
    @endif

    <nav class="tabs mb-6">
        @foreach (\App\Services\MasterService::TYPES as $t => $label)
            <a href="{{ $r('index', ['type' => $t]) }}" class="tab {{ $type === $t ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($type === 'stages')
        <div class="mb-4 flex gap-2">
            @foreach (['Village', 'Town', 'City'] as $a)
                <a href="{{ $r('index', ['type' => 'stages', 'area' => $a]) }}" class="{{ $area === $a ? 'btn-primary' : 'btn-light' }} btn-sm">{{ $a }}</a>
            @endforeach
        </div>
        <div class="space-y-4">
            @forelse ($stages as $st)
                <details class="card" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 p-4">
                        <span class="font-bold">Stage {{ $st->stage_no }} · {{ $st->name }}</span>
                        <span class="text-sm text-muted">{{ $st->subtasks->count() }} sub-tasks · {{ $st->subtasks->sum('default_duration_days') }} days total</span>
                    </summary>
                    <div class="border-t border-navy-50">
                        <div class="table-wrap"><table class="tbl">
                            <thead><tr><th>Task</th><th>Sub-task</th><th>Depends on</th><th class="num">Days</th><th class="num">Cost</th><th>Documents</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($st->subtasks as $t)
                                <tr class="{{ $t->is_active ? '' : 'opacity-50' }}">
                                    <td class="font-mono text-xs font-semibold">{{ $t->task_code }}</td>
                                    <td><p class="font-semibold">{{ $t->short_name }}</p><p class="text-xs text-muted">{{ $t->name }}</p><p class="text-xs text-muted">{{ $t->responsible }}</p></td>
                                    <td class="text-xs">{{ $t->dependencies->pluck('task_code')->implode(', ') ?: '—' }}</td>
                                    <td class="num">{{ $t->default_duration_days }}</td>
                                    <td class="num">@inr($t->default_cost)</td>
                                    <td class="text-xs">{{ $t->documents->pluck('doc_code')->implode(', ') ?: '—' }}</td>
                                    <td class="text-right">
                                        @if ($canEdit)
                                            <details class="relative" data-dropdown><summary class="btn-light btn-sm list-none cursor-pointer">Edit</summary>
                                                <form method="POST" action="{{ $r('update', ['type' => 'stages', 'id' => $t->id]) }}" class="absolute right-0 z-20 mt-2 grid w-[min(36rem,90vw)] gap-3 rounded-xl bg-white p-4 text-left shadow-lift ring-1 ring-navy-50 sm:grid-cols-2">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="kind" value="subtask"><input type="hidden" name="stage_master_id" value="{{ $st->id }}">
                                                    <x-field name="task_code" label="Task ID" :value="$t->task_code" id="t{{ $t->id }}c" required />
                                                    <x-field name="short_name" label="Short name" :value="$t->short_name" id="t{{ $t->id }}s" required />
                                                    <x-field name="name" label="Sub-task" :value="$t->name" id="t{{ $t->id }}n" required class="sm:col-span-2" />
                                                    <x-field name="responsible" label="Responsible / authority" :value="$t->responsible" id="t{{ $t->id }}r" />
                                                    <x-field name="output" label="Output / document" :value="$t->output" id="t{{ $t->id }}o" />
                                                    <x-field name="default_duration_days" type="number" label="Default duration (days)" :value="$t->default_duration_days" id="t{{ $t->id }}d" required />
                                                    <x-field name="default_cost" type="number" step="0.01" label="Default cost (₹)" :value="$t->default_cost" id="t{{ $t->id }}k" />
                                                    <x-field name="depends_on" label="Depends on (task IDs)" :value="$t->dependencies->pluck('task_code')->implode(', ')" id="t{{ $t->id }}p" />
                                                    <x-field name="doc_codes" label="Required document IDs" :value="$t->documents->pluck('doc_code')->implode(', ')" id="t{{ $t->id }}x" />
                                                    <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active" :checked="$t->is_active" />
                                                    <div class="flex gap-2 sm:col-span-2"><button class="btn-primary btn-sm">Save</button></div>
                                                </form>
                                            </details>
                                        @endif
                                        @if ($canDelete)
                                            <form method="POST" action="{{ $r('destroy', ['type' => 'stages', 'id' => $t->id]) }}" class="inline" data-confirm="Delete sub-task {{ $t->task_code }}?">@csrf @method('DELETE')<input type="hidden" name="kind" value="subtask"><button class="btn-ghost btn-sm text-red-700">Delete</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                        @if ($canCreate)
                            <details class="border-t border-navy-50 p-4">
                                <summary class="cursor-pointer text-sm font-semibold text-teal-700">+ Add sub-task to stage {{ $st->stage_no }}</summary>
                                <form method="POST" action="{{ $r('store', ['type' => 'stages']) }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @csrf
                                    <input type="hidden" name="kind" value="subtask"><input type="hidden" name="stage_master_id" value="{{ $st->id }}">
                                    <x-field name="task_code" label="Task ID" id="n{{ $st->id }}c" required placeholder="{{ substr($area, 0, 1) }}{{ $st->stage_no }}.{{ $st->subtasks->count() + 1 }}" />
                                    <x-field name="short_name" label="Short name" id="n{{ $st->id }}s" required />
                                    <x-field name="name" label="Sub-task" id="n{{ $st->id }}n" required class="sm:col-span-2" />
                                    <x-field name="responsible" label="Responsible" id="n{{ $st->id }}r" />
                                    <x-field name="default_duration_days" type="number" label="Days" id="n{{ $st->id }}d" required value="1" />
                                    <x-field name="depends_on" label="Depends on" id="n{{ $st->id }}p" />
                                    <x-field name="doc_codes" label="Document IDs" id="n{{ $st->id }}x" />
                                    <div><button class="btn-primary btn-sm">Add</button></div>
                                </form>
                            </details>
                        @endif
                        @if ($canEdit || $canDelete)
                            <div class="flex flex-wrap items-end gap-2 border-t border-navy-50 p-4">
                                @if ($canEdit)
                                    <form method="POST" action="{{ $r('update', ['type' => 'stages', 'id' => $st->id]) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf @method('PUT')<input type="hidden" name="kind" value="stage"><input type="hidden" name="area_type" value="{{ $st->area_type }}">
                                        <x-field name="stage_no" type="number" label="Stage no." :value="$st->stage_no" id="s{{ $st->id }}no" class="w-24" />
                                        <x-field name="name" label="Stage name" :value="$st->name" id="s{{ $st->id }}nm" />
                                        <x-field name="default_cost" type="number" step="0.01" label="Approval cost (₹)" :value="$st->default_cost" id="s{{ $st->id }}dc" />
                                        <button class="btn-light btn-sm">Save stage</button>
                                    </form>
                                @endif
                                @if ($canDelete)
                                    <form method="POST" action="{{ $r('destroy', ['type' => 'stages', 'id' => $st->id]) }}" data-confirm="Delete stage {{ $st->stage_no }} and all its sub-tasks?">@csrf @method('DELETE')<input type="hidden" name="kind" value="stage"><button class="btn-ghost btn-sm text-red-700">Delete stage</button></form>
                                @endif
                            </div>
                        @endif
                    </div>
                </details>
            @empty
                <div class="card"><x-empty title="No stages for {{ $area }} yet" icon="layers">Import the template or use the App defaults.</x-empty></div>
            @endforelse
        </div>
        @if ($canCreate)
            <form method="POST" action="{{ $r('store', ['type' => 'stages']) }}" class="card card-pad mt-6 flex flex-wrap items-end gap-3">
                @csrf <input type="hidden" name="kind" value="stage"><input type="hidden" name="area_type" value="{{ $area }}">
                <x-field name="stage_no" type="number" label="Stage no." class="w-28" required id="ns_no" />
                <x-field name="name" label="New {{ $area }} stage" required id="ns_name" />
                <x-field name="default_cost" type="number" step="0.01" label="Approval cost (₹)" id="ns_cost" />
                <button class="btn-primary">Add stage</button>
            </form>
        @endif

    @elseif ($type === 'facilities')
        <div class="card"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>ID</th><th>Facility</th><th>Unit</th><th class="text-center">Basic</th><th class="text-center">Standard</th><th class="text-center">Premium</th><th class="num">Village ₹</th><th class="num">Town ₹</th><th class="num">City ₹</th><th></th></tr></thead>
            <tbody>
            @foreach ($facilities as $f)
                <tr class="{{ $f->is_active ? '' : 'opacity-50' }}">
                    <td class="font-mono text-xs">{{ $f->facility_code }}</td>
                    <td><p class="font-semibold">{{ $f->name }} @if ($f->is_statutory)<span class="badge-navy ml-1">Statutory</span>@endif</p><p class="text-xs text-muted">{{ $f->category }} · {{ $f->specification }}</p></td>
                    <td class="text-sm">{{ $f->unit }}</td>
                    @foreach (['tier_basic', 'tier_standard', 'tier_premium'] as $tier)<td class="text-center text-sm font-semibold">{{ $f->$tier }}</td>@endforeach
                    <td class="num">@inr($f->cost_village)</td><td class="num">@inr($f->cost_town)</td><td class="num">@inr($f->cost_city)</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($canEdit)
                            <details class="relative inline-block" data-dropdown><summary class="btn-light btn-sm list-none cursor-pointer">Edit</summary>
                                <form method="POST" action="{{ $r('update', ['type' => 'facilities', 'id' => $f->id]) }}" class="absolute right-0 z-20 mt-2 grid w-[min(36rem,90vw)] gap-3 rounded-xl bg-white p-4 text-left shadow-lift ring-1 ring-navy-50 sm:grid-cols-3">
                                    @csrf @method('PUT')
                                    @include('masters.facility-fields', ['f' => $f, 'p' => 'f'.$f->id])
                                    <div class="sm:col-span-3"><button class="btn-primary btn-sm">Save</button></div>
                                </form>
                            </details>
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ $r('destroy', ['type' => 'facilities', 'id' => $f->id]) }}" class="inline" data-confirm="Delete {{ $f->name }}?">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-700">Delete</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
        @if ($canCreate)
            <form method="POST" action="{{ $r('store', ['type' => 'facilities']) }}" class="card card-pad mt-6 grid gap-3 sm:grid-cols-3">
                @csrf
                <h2 class="section-title sm:col-span-3">Add facility</h2>
                @include('masters.facility-fields', ['f' => new \App\Models\FacilityMaster(['tier_basic' => '-', 'tier_standard' => 'Y', 'tier_premium' => 'Y', 'is_active' => true]), 'p' => 'nf'])
                <div class="sm:col-span-3"><button class="btn-primary">Add facility</button></div>
            </form>
        @endif

    @elseif ($type === 'documents')
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <input type="hidden" name="type" value="documents">
            <x-select name="checklist" label="Checklist" :options="$checklists->combine($checklists)" :value="request('checklist')" placeholder="All checklists" data-autosubmit />
        </form>
        <div class="card"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>Doc ID</th><th>Document</th><th>Checklist</th><th>Mandatory</th><th>Stage</th><th>Village / Town / City task</th><th></th></tr></thead>
            <tbody>
            @foreach ($documents as $d)
                <tr class="{{ $d->is_active ? '' : 'opacity-50' }}">
                    <td class="font-mono text-xs">{{ $d->doc_code }}</td>
                    <td><p class="font-semibold">{{ $d->name }}</p><p class="text-xs text-muted">{{ $d->issued_by }} · {{ $d->submission_format }}</p>@if ($d->remarks)<p class="text-xs text-muted">{{ $d->remarks }}</p>@endif</td>
                    <td class="text-sm">{{ $d->checklist }}</td>
                    <td><span class="{{ $d->mandatory === 'Yes' ? 'badge-navy' : ($d->mandatory === 'Conditional' ? 'badge-amber' : 'badge-gray') }}">{{ $d->mandatory }}</span></td>
                    <td class="text-sm">{{ $d->stage_no }} {{ $d->stage_name }}</td>
                    <td class="font-mono text-xs">{{ $d->village_task_code ?? 'N/A' }} / {{ $d->town_task_code ?? 'N/A' }} / {{ $d->city_task_code ?? 'N/A' }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($canEdit)
                            <details class="relative inline-block" data-dropdown><summary class="btn-light btn-sm list-none cursor-pointer">Edit</summary>
                                <form method="POST" action="{{ $r('update', ['type' => 'documents', 'id' => $d->id]) }}" class="absolute right-0 z-20 mt-2 grid w-[min(36rem,90vw)] gap-3 rounded-xl bg-white p-4 text-left shadow-lift ring-1 ring-navy-50 sm:grid-cols-3">
                                    @csrf @method('PUT')
                                    @include('masters.document-fields', ['d' => $d, 'p' => 'd'.$d->id])
                                    <div class="sm:col-span-3"><button class="btn-primary btn-sm">Save</button></div>
                                </form>
                            </details>
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ $r('destroy', ['type' => 'documents', 'id' => $d->id]) }}" class="inline" data-confirm="Delete {{ $d->doc_code }}?">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-700">Delete</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
        @if ($canCreate)
            <form method="POST" action="{{ $r('store', ['type' => 'documents']) }}" class="card card-pad mt-6 grid gap-3 sm:grid-cols-3">
                @csrf
                <h2 class="section-title sm:col-span-3">Add document</h2>
                @include('masters.document-fields', ['d' => new \App\Models\DocumentChecklist(['mandatory' => 'Yes', 'applies_to' => 'All', 'is_active' => true]), 'p' => 'nd'])
                <div class="sm:col-span-3"><button class="btn-primary">Add document</button></div>
            </form>
        @endif

    @else
        <div class="card"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>District</th><th>Taluk</th><th>Sub-Registrar Office</th><th>Address</th><th></th></tr></thead>
            <tbody>
            @foreach ($sros as $s)
                <tr><td>{{ $s->district }}</td><td>{{ $s->taluk }}</td><td class="font-semibold">{{ $s->name }}</td><td class="text-sm">{{ $s->address }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($canEdit)
                            <details class="relative inline-block" data-dropdown><summary class="btn-light btn-sm list-none cursor-pointer">Edit</summary>
                                <form method="POST" action="{{ $r('update', ['type' => 'sros', 'id' => $s->id]) }}" class="absolute right-0 z-20 mt-2 w-80 space-y-2 rounded-xl bg-white p-4 text-left shadow-lift ring-1 ring-navy-50">
                                    @csrf @method('PUT')
                                    <x-field name="state" label="State" :value="$s->state" id="sr{{ $s->id }}st" />
                                    <x-field name="district" label="District" :value="$s->district" id="sr{{ $s->id }}d" required />
                                    <x-field name="taluk" label="Taluk" :value="$s->taluk" id="sr{{ $s->id }}t" />
                                    <x-field name="name" label="SRO name" :value="$s->name" id="sr{{ $s->id }}n" required />
                                    <x-field name="address" label="Address" :value="$s->address" id="sr{{ $s->id }}a" />
                                    <button class="btn-primary btn-sm">Save</button>
                                </form>
                            </details>
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ $r('destroy', ['type' => 'sros', 'id' => $s->id]) }}" class="inline" data-confirm="Delete {{ $s->name }}?">@csrf @method('DELETE')<button class="btn-ghost btn-sm text-red-700">Delete</button></form>
                        @endif
                    </td></tr>
            @endforeach
            </tbody>
        </table></div></div>
        @if ($canCreate)
            <form method="POST" action="{{ $r('store', ['type' => 'sros']) }}" class="card card-pad mt-6 grid gap-3 sm:grid-cols-5">
                @csrf
                <x-field name="state" label="State" value="Tamil Nadu" id="nsr_st" />
                <x-field name="district" label="District" required id="nsr_d" />
                <x-field name="taluk" label="Taluk" id="nsr_t" />
                <x-field name="name" label="SRO name" required id="nsr_n" />
                <div class="flex items-end"><button class="btn-primary">Add SRO</button></div>
            </form>
        @endif
    @endif
</x-layouts.workspace>
