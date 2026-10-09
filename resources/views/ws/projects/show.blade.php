<x-layouts.workspace :title="$project->name">
    @php($e = $project->estimate)
    @php($u = auth()->user())
    <x-page-header :title="$project->name" :subtitle="$project->project_code.' · '.$project->approval_type.' approval · '.$project->location.', '.$project->district" :back="route('ws.projects.index')">
        @if (! $project->isReadOnly() && $u->hasPerm('projects.update'))<a href="{{ route('ws.projects.edit', $project) }}" class="btn-light"><x-icon name="edit" class="h-4 w-4" /> Edit</a>@endif
        @if ($u->hasPerm('estimates.view') && \App\Services\PlanLimiter::moduleEnabled($project->tenantRel, 'estimates'))<a href="{{ route('ws.estimate.edit', $project) }}" class="btn-light"><x-icon name="rupee" class="h-4 w-4" /> Estimate</a>@endif
        @if ($project->stages->isNotEmpty() && $u->hasPerm('tracking.view'))<a href="{{ route('ws.tracking.show', $project) }}" class="btn-primary"><x-icon name="clipboard" class="h-4 w-4" /> Tracking</a>@endif
    </x-page-header>

    {{-- Status flow --}}
    @php($flow = ['draft', 'init', 'go_no_go', 'in_progress', 'ready_to_launch', 'launched'])
    @php($idx = array_search($project->status, $flow))
    <ol class="mb-6 flex gap-1 overflow-x-auto pb-1" aria-label="Project status">
        @foreach ($flow as $i => $st)
            <li class="flex min-w-[8.5rem] flex-1 items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold {{ $project->status === 'closed' ? 'bg-page text-muted' : ($i < $idx ? 'bg-teal-50 text-teal-800' : ($i === $idx ? 'bg-navy text-white' : 'bg-white text-muted ring-1 ring-navy-50')) }}" @if ($i === $idx) aria-current="step" @endif>
                <span>{{ $i + 1 }}</span>{{ \App\Models\Project::STATUSES[$st] }}
            </li>
        @endforeach
        @if ($project->status === 'closed')<li class="flex min-w-[7rem] items-center rounded-lg bg-red-700 px-3 py-2 text-xs font-semibold text-white">Closed</li>@endif
    </ol>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Next step --}}
            @if (in_array($project->status, ['draft', 'init']))
                <div class="card card-pad ring-2 ring-teal">
                    <p class="eyebrow">Step 2 · Project init</p>
                    <h2 class="mt-1 text-xl font-extrabold">Prepare the estimate & budget</h2>
                    <p class="mt-1 text-sm text-muted">Choose the approval stages and facilities for a tier. vector7 calculates production cost, sellable area, cost per sq ft, MRP and the estimated duration.</p>
                    @can('estimates.update')<a href="{{ route('ws.estimate.edit', $project) }}" class="btn-primary mt-4">Open estimate</a>@endcan
                </div>
            @elseif ($project->status === 'go_no_go' && ! $project->decision)
                @can('projects.approve')
                    <form method="POST" action="{{ route('ws.projects.decision', $project) }}" class="card card-pad space-y-3 ring-2 ring-amber-400">
                        @csrf
                        <p class="eyebrow">Decision</p>
                        <h2 class="text-xl font-extrabold">Go / No-Go</h2>
                        <p class="text-sm text-muted">Budget {{ \App\Support\Format::inr($e?->total_cost) }} · MRP {{ \App\Support\Format::inr($e?->mrp_per_sqft, 2) }}/sq ft · about {{ $e?->est_duration_days }} working days. No-Go closes the project (read-only).</p>
                        <x-textarea name="notes" label="Notes" rows="2" />
                        <div class="flex gap-2">
                            <button name="decision" value="go" class="btn-teal">Go</button>
                            <button name="decision" value="no_go" class="btn-danger" formnovalidate>No-Go</button>
                        </div>
                    </form>
                @else
                    <div class="card card-pad text-sm text-muted">Waiting for the Go / No-Go decision by a Tenant Admin.</div>
                @endcan
            @elseif ($project->status === 'go_no_go' && $project->decision === 'go')
                @can('projects.update')
                    <form method="POST" action="{{ route('ws.projects.start', $project) }}" class="card card-pad space-y-3 ring-2 ring-teal">
                        @csrf
                        <p class="eyebrow">Go · {{ \App\Support\Format::date($project->decision_on) }} by {{ $project->decisionMaker?->name }}</p>
                        <h2 class="text-xl font-extrabold">Start the project</h2>
                        <p class="text-sm text-muted">The estimated end date is calculated from the stage durations and dependencies in working days. You can adjust it.</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field name="start_date" type="date" label="Start date" :value="today()->toDateString()" required />
                            <x-field name="est_end_date" type="date" label="Estimated end (optional override)" />
                        </div>
                        <button class="btn-primary">Start & create stages</button>
                    </form>
                @endcan
            @elseif ($project->status === 'in_progress')
                <div class="card card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><p class="eyebrow">In progress</p><h2 class="text-xl font-extrabold">{{ (float) $project->progress_pct }}% complete</h2></div>
                        @if (! $gaps && $u->hasPerm('tracking.update'))
                            <x-confirm :action="route('ws.tracking.ready', $project)" message="Mark {{ $project->name }} as Ready to Launch?" class="btn-teal">Mark Ready to Launch</x-confirm>
                        @endif
                    </div>
                    <x-progress :pct="$project->progress_pct" level="teal" class="mt-3" label="Project progress" />
                    @if ($gaps)<p class="mt-3 text-sm text-muted">{{ count($gaps) }} approval task(s) left before Ready to Launch.</p>@endif
                </div>
            @elseif ($project->status === 'ready_to_launch')
                <div class="card card-pad ring-2 ring-teal">
                    <p class="eyebrow">Ready to launch</p>
                    <h2 class="text-xl font-extrabold">Approvals complete — launch the layout</h2>
                    @can('launch.view')<a href="{{ route('ws.launch.show', $project) }}" class="btn-primary mt-3">Go to launch</a>@endcan
                </div>
            @elseif ($project->status === 'launched')
                <div class="card card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-2"><div><p class="eyebrow">Launched {{ \App\Support\Format::date($project->launched_at) }}</p><h2 class="text-xl font-extrabold">On the marketplace</h2></div>
                        <div class="flex gap-2"><a href="{{ $project->publicUrl() }}" class="btn-light" target="_blank" rel="noopener">View public page</a>@can('launch.view')<a href="{{ route('ws.launch.show', $project) }}" class="btn-primary">Plots</a>@endcan</div></div>
                    <div class="mt-4 flex flex-wrap gap-2">@foreach (\App\Models\Plot::STATUSES as $k => $l)@if ($plotStats[$k] ?? 0)<x-status :status="$k" :label="$l.': '.$plotStats[$k]" />@endif @endforeach</div>
                </div>
            @else
                <div class="card card-pad"><p class="eyebrow text-red-700">Closed</p><p class="mt-1 text-sm">{{ $project->decision === 'no_go' ? 'No-Go on '.\App\Support\Format::date($project->decision_on).'. '.$project->decision_notes : 'This project is closed and read-only.' }}</p></div>
            @endif

            {{-- Stages --}}
            @if ($project->stages->isNotEmpty())
                <div class="card">
                    <h2 class="section-title p-4">Stages</h2>
                    <div class="table-wrap"><table class="tbl">
                        <thead><tr><th>Stage</th><th class="w-1/4">Progress</th><th>Planned</th><th class="num">Budget</th><th class="num">Actual</th></tr></thead>
                        <tbody>
                        @foreach ($project->stages as $s)
                            @php($over = $s->budget > 0 && $s->actual_cost > $s->budget)
                            <tr><td class="font-semibold">{{ $s->stage_no }}. {{ $s->name }}</td>
                                <td><div class="flex items-center gap-2"><x-progress :pct="$s->progress_pct" level="teal" class="flex-1" :label="$s->name" /><span class="text-xs">{{ (float) $s->progress_pct }}%</span></div></td>
                                <td class="whitespace-nowrap text-xs">@date($s->planned_start) – @date($s->planned_end)</td>
                                <td class="num">@inr($s->budget)</td><td class="num {{ $over ? 'font-bold text-red-700' : '' }}">@inr($s->actual_cost)</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card card-pad">
                <h2 class="section-title">Overview</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-muted">Status</dt><dd><x-project-status :status="$project->status" /></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Size</dt><dd class="text-right font-semibold">{{ (float) $project->size_acres }} acres<br><span class="text-xs text-muted">{{ \App\Support\Format::num($project->totalSqft()) }} sq ft</span></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Land</dt><dd>{{ $project->land_classification ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Survey no.</dt><dd class="text-right">{{ $project->survey_numbers ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Patta no.</dt><dd class="text-right">{{ $project->patta_numbers ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Guideline value</dt><dd class="text-right">@inr($project->guideline_value)<br><span class="text-xs text-muted">@inr($project->guideline_rate)/sq ft</span></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Market value</dt><dd class="text-right">@inr($project->market_value)<br><span class="text-xs text-muted">@inr($project->market_rate)/sq ft</span></dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Owner</dt><dd>{{ $project->owner_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Manager</dt><dd>{{ $project->manager?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Start / est. end</dt><dd>@date($project->start_date) / @date($project->est_end_date)</dd></div>
                </dl>
            </div>
            @if ($e)
                <div class="card card-pad">
                    <div class="flex items-center justify-between"><h2 class="section-title">Estimate · {{ $e->tier }}</h2>
                        @can('estimates.export')<div class="flex gap-1"><a href="{{ route('ws.estimate.export', [$project, 'pdf']) }}" class="btn-ghost btn-sm">PDF</a><a href="{{ route('ws.estimate.export', [$project, 'xlsx']) }}" class="btn-ghost btn-sm">Excel</a></div>@endcan</div>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Facilities</dt><dd>@inr($e->facility_cost)</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Approvals / stages</dt><dd>@inr($e->stage_cost)</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Other</dt><dd>@inr($e->other_cost)</dd></div>
                        <div class="flex justify-between border-t border-navy-50 pt-2 font-bold"><dt>Total production cost</dt><dd>@inr($e->total_cost)</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Sellable area</dt><dd>{{ \App\Support\Format::num($e->sellable_sqft) }} sq ft</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Cost / sellable sq ft</dt><dd>{{ \App\Support\Format::inr($e->cost_per_sqft, 2) }}</dd></div>
                        <div class="flex justify-between font-bold text-teal-700"><dt>MRP / sq ft (× {{ (float) $e->mrp_multiplier }})</dt><dd>{{ \App\Support\Format::inr($e->mrp_per_sqft, 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Duration</dt><dd>{{ $e->est_duration_days }} working days</dd></div>
                        @if (in_array($project->status, ['in_progress', 'ready_to_launch', 'launched']))
                            <div class="flex justify-between border-t border-navy-50 pt-2"><dt class="text-muted">Spent so far</dt><dd class="{{ $spend > $e->total_cost ? 'font-bold text-red-700' : '' }}">@inr($spend)</dd></div>
                            <x-progress :pct="$e->total_cost > 0 ? $spend / $e->total_cost * 100 : 0" label="Budget used" />
                        @endif
                    </dl>
                </div>
            @endif
            @if (! in_array($project->status, ['launched']) && $u->hasPerm('projects.delete'))
                <x-confirm :action="route('ws.projects.destroy', $project)" method="DELETE" message="Delete project {{ $project->name }}? It moves to the bin and stops counting towards your plan." class="btn-ghost btn-sm text-red-700">Delete project</x-confirm>
            @endif
        </div>
    </div>
</x-layouts.workspace>
