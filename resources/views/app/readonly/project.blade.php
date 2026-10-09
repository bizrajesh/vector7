<x-layouts.workspace :title="$p->name">
    <x-page-header :title="$p->name" :subtitle="$p->tenantRel?->name.' · '.$p->project_code.' · '.$p->location.', '.$p->district" :back="route('app.projects.index')">
        <x-project-status :status="$p->status" />
        @if ($p->status === 'launched')<a href="{{ $p->publicUrl() }}" class="btn-light" target="_blank" rel="noopener"><x-icon name="globe" class="h-4 w-4" /> Marketplace page</a>@endif
    </x-page-header>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Progress" :value="round($p->progress_pct).'%'" icon="chart" />
        <x-stat label="Land" :value="\App\Support\Format::num($p->size_acres, 2).' acres'" icon="ruler" tone="navy" :sub="\App\Support\Format::num($p->totalSqft()).' sq ft'" />
        <x-stat label="Estimate" :value="$p->estimate ? \App\Support\Format::inrShort($p->estimate->total_cost) : '—'" icon="rupee" tone="gold" :sub="$p->estimate ? 'MRP '.\App\Support\Format::inr($p->estimate->mrp_per_sqft).' / sq ft' : null" />
        <x-stat label="Timeline" :value="$p->start_date ? \App\Support\Format::date($p->start_date) : 'Not started'" icon="calendar" tone="navy" :sub="$p->est_end_date ? 'Est. end '.\App\Support\Format::date($p->est_end_date) : null" />
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <h2 class="section-title p-4">Approval stages</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>#</th><th>Stage</th><th>Planned</th><th class="num">Budget</th><th class="num">Spent</th><th class="w-40">Progress</th></tr></thead>
                <tbody>
                @forelse ($p->stages as $s)
                    <tr>
                        <td>{{ $s->stage_no }}</td>
                        <td class="font-semibold">{{ $s->name }}</td>
                        <td class="text-xs">@date($s->planned_start) – @date($s->planned_end)</td>
                        <td class="num">@inr($s->budget)</td>
                        <td class="num">@inr($s->actual_cost)</td>
                        <td><x-progress :pct="$s->progress_pct" level="teal" :label="$s->name" /><p class="mt-1 text-xs text-muted">{{ round($s->progress_pct) }}%</p></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty title="Stages appear after the project is started" icon="layers" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="space-y-6">
            <div class="card card-pad">
                <h2 class="section-title">Plot inventory</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($plots as $status => $row)
                        <li class="flex items-center justify-between"><x-status :status="$status" /><span class="tabular-nums">{{ $row->n }} plot(s) · {{ \App\Support\Format::num($row->sqft) }} sq ft</span></li>
                    @empty
                        <li class="text-muted">No plots loaded yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card card-pad text-sm">
                <h2 class="section-title">Details</h2>
                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
                    <dt class="text-muted">Approval</dt><dd>{{ $p->approval_type }}</dd>
                    <dt class="text-muted">Survey no(s).</dt><dd>{{ $p->survey_numbers ?: '—' }}</dd>
                    <dt class="text-muted">Patta no(s).</dt><dd>{{ $p->patta_numbers ?: '—' }}</dd>
                    <dt class="text-muted">Classification</dt><dd>{{ $p->land_classification ?: '—' }}</dd>
                    <dt class="text-muted">Guideline value</dt><dd>@inr($p->guideline_value)</dd>
                    <dt class="text-muted">Market value</dt><dd>@inr($p->market_value)</dd>
                    <dt class="text-muted">Manager</dt><dd>{{ $p->manager?->name ?? '—' }}</dd>
                    <dt class="text-muted">Decision</dt><dd>{{ $p->decision ? strtoupper($p->decision).' · '.\App\Support\Format::date($p->decision_on) : '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</x-layouts.workspace>
