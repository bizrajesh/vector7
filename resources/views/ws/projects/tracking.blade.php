<x-layouts.workspace :title="'Tracking — '.$project->name">
    <x-page-header title="Approval tracking" :subtitle="$project->name.' · '.$project->project_code.' · '.(float) $project->progress_pct.'% complete'" :back="route('ws.projects.show', $project)">
        @if ($project->status === 'in_progress' && ! $gaps && auth()->user()->hasPerm('tracking.update'))
            <x-confirm :action="route('ws.tracking.ready', $project)" message="Mark {{ $project->name }} as Ready to Launch?" class="btn-teal">Mark Ready to Launch</x-confirm>
        @endif
    </x-page-header>
    @if ($project->status !== 'in_progress')<div class="flash-warn mb-4">Tracking is read-only because the project is {{ $project->statusLabel() }}.</div>@endif

    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        <x-stat label="Progress" :value="(float) $project->progress_pct.'%'" icon="chart" />
        <x-stat label="Delayed sub-tasks" :value="$delayed" icon="clock" :tone="$delayed ? 'red' : 'teal'" />
        <x-stat label="Left before launch" :value="count($gaps)" icon="clipboard" tone="navy" />
        <x-stat label="Est. end" :value="\App\Support\Format::date($project->est_end_date)" icon="calendar" tone="gold" />
    </div>
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'open' => 'Open', 'delayed' => 'Delayed', 'blocked' => 'Blocked'] as $k => $l)
            <a href="{{ route('ws.tracking.show', ['project' => $project, 'filter' => $k ?: null]) }}" class="{{ (string) $filter === (string) $k ? 'btn-primary' : 'btn-light' }} btn-sm">{{ $l }}</a>
        @endforeach
    </div>

    @php($statusCls = ['not_started' => 'badge-gray', 'in_progress' => 'badge-blue', 'blocked' => 'badge-red', 'done' => 'badge-teal'])
    <div class="space-y-4">
        @foreach ($project->stages as $stage)
            @php($subs = $stage->subtasks->filter(fn ($s) => match ($filter) { 'open' => $s->status !== 'done', 'delayed' => $s->status !== 'done' && $s->planned_end && $s->planned_end->lt(today()), 'blocked' => $s->status === 'blocked', default => true }))
            @continue($subs->isEmpty())
            <details class="card" @if ($stage->status !== 'done' || $filter) open @endif>
                <summary class="flex cursor-pointer flex-wrap items-center gap-3 p-4">
                    <span class="font-bold">{{ $stage->stage_no }}. {{ $stage->name }}</span>
                    <span class="{{ $statusCls[$stage->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_', ' ', $stage->status)) }}</span>
                    <span class="ml-auto flex w-full items-center gap-2 sm:w-64"><x-progress :pct="$stage->progress_pct" level="teal" class="flex-1" :label="$stage->name" /><span class="text-xs font-semibold">{{ (float) $stage->progress_pct }}%</span></span>
                </summary>
                <div class="divide-y divide-navy-50 border-t border-navy-50">
                    @foreach ($subs as $s)
                        @php($late = $s->status !== 'done' && $s->planned_end && $s->planned_end->lt(today()))
                        <div class="grid gap-4 p-4 lg:grid-cols-5">
                            <div class="lg:col-span-2">
                                <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $s->task_code }}</span><span class="font-semibold">{{ $s->short_name }}</span>
                                    <span class="{{ $statusCls[$s->status] }}">{{ \App\Models\ProjectSubtask::STATUSES[$s->status] }}</span>@if ($late)<span class="badge-red">Delayed</span>@endif</p>
                                <p class="mt-1 text-sm text-muted">{{ $s->name }}</p>
                                <p class="mt-1 text-xs text-muted">{{ $s->responsible }} · Depends on: {{ implode(', ', $s->depends_on ?? []) ?: 'none' }}</p>
                                <p class="mt-1 text-xs">Planned @date($s->planned_start) – @date($s->planned_end) ({{ $s->duration_days }} d) · Actual @date($s->actual_start) – @date($s->actual_end)</p>
                                <p class="text-xs">Budget @inr($s->budget) · Spent <span class="{{ $s->budget > 0 && $s->actual_cost > $s->budget ? 'font-bold text-red-700' : '' }}">@inr($s->actual_cost)</span></p>
                                @if ($s->notes)<p class="mt-1 rounded-lg bg-page p-2 text-xs">{{ $s->notes }}</p>@endif
                            </div>
                            <div class="lg:col-span-1">
                                <p class="text-xs font-bold uppercase tracking-wide text-muted">Documents</p>
                                <ul class="mt-1 space-y-1.5">
                                    @forelse ($s->documents as $d)
                                        <li class="text-xs">
                                            <span class="font-mono font-semibold">{{ $d->doc_code }}</span> {{ \Illuminate\Support\Str::limit($d->name, 42) }}
                                            @if ($d->is_mandatory)<span class="text-red-700" title="Mandatory">*</span>@endif
                                            @if ($d->file)<a href="{{ route('files.show', $d->file) }}" target="_blank" rel="noopener" class="ml-1 font-semibold">View</a>@else<span class="ml-1 text-amber-800">missing</span>@endif
                                            @if ($canEdit)
                                                <form method="POST" action="{{ route('ws.tracking.upload', [$project, $d]) }}" enctype="multipart/form-data" class="mt-1 flex items-center gap-1">@csrf
                                                    <input type="file" name="file" required class="block w-full text-[11px] file:mr-2 file:rounded file:border-0 file:bg-page file:px-2 file:py-1" accept=".pdf,.jpg,.jpeg,.png,.webp,.docx" aria-label="Upload {{ $d->doc_code }}">
                                                    <button class="btn-light btn-sm">Upload</button>
                                                </form>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="text-xs text-muted">No documents required.</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="lg:col-span-2">
                                @if ($canEdit)
                                    <form method="POST" action="{{ route('ws.tracking.update', [$project, $s]) }}" class="grid grid-cols-2 gap-2">
                                        @csrf @method('PUT')
                                        <x-select name="status" label="Status" :options="\App\Models\ProjectSubtask::STATUSES" :value="$s->status" id="st{{ $s->id }}" />
                                        <x-field name="actual_cost" type="number" step="0.01" label="Actual cost ₹" :value="(float) $s->actual_cost" id="ac{{ $s->id }}" />
                                        <x-field name="actual_start" type="date" label="Actual start" :value="$s->actual_start?->toDateString()" id="as{{ $s->id }}" />
                                        <x-field name="actual_end" type="date" label="Actual end" :value="$s->actual_end?->toDateString()" id="ae{{ $s->id }}" />
                                        <x-textarea name="notes" label="Notes" :value="$s->notes" rows="2" id="no{{ $s->id }}" class="col-span-2" />
                                        <div class="col-span-2"><button class="btn-primary btn-sm">Update {{ $s->task_code }}</button></div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
</x-layouts.workspace>
