<div class="grid gap-4 lg:grid-cols-3">
    <section class="grid grid-cols-2 gap-3 lg:col-span-2 lg:grid-cols-4">
        <x-kpi label="Total area" :value="number_format((float) $layout->total_sqft).' ft²'" :note="round((float) $layout->total_sqft / 43560, 2).' acres'" />
        <x-kpi label="Sellable area" :value="number_format($estimate['sellable_sqft']).' ft²'" :note="(float) $layout->sellable_pct.'% · ~'.$estimate['est_plots'].' plots'" />
        <x-kpi label="Production value" :value="\App\Support\Money::short($estimate['production_value'])" :note="'₹'.number_format($estimate['cost_per_sellable_sqft'], 0).' per sellable ft²'" />
        <x-kpi label="Actual spend" :value="\App\Support\Money::short($actualCost)"
            :note="$estimate['total_cost'] > 0 ? round($actualCost / max(1, $estimate['total_cost'] - $estimate['land_cost']) * 100).'% of work budget' : null"
            :tone="$estimate['total_cost'] > 0 && $actualCost > ($estimate['total_cost'] - $estimate['land_cost']) ? 'bad' : 'muted'" />
    </section>
    <section class="card-pad">
        <h2 class="section-title mb-3">Plots</h2>
        @if ($plotCounts)
            @foreach (\App\Enums\PlotStatus::cases() as $s)
                @if ($plotCounts[$s->value] ?? 0)<div class="flex justify-between py-1 text-sm"><x-status :status="$s" /><span class="num font-semibold">{{ $plotCounts[$s->value] }}</span></div>@endif
            @endforeach
        @else
            <p class="text-sm text-ink-muted">Plots are onboarded once all mandatory stages are complete.</p>
        @endif
    </section>
    <section class="card-pad lg:col-span-2">
        <h2 class="section-title mb-3">Stage progress</h2>
        @forelse ($layout->stages as $stage)
            <div class="flex items-center gap-3 border-t border-line-soft py-2.5 text-sm first:border-t-0">
                <span class="flex-1 font-semibold">{{ $stage->name }}</span>
                <span class="progress w-24"><span style="width: {{ (float) $stage->progress_pct }}%"></span></span>
                <span class="{{ ['pending' => 'badge-rs', 'in_progress' => 'badge-os', 'completed' => 'badge-av', 'skipped' => 'badge-bk'][$stage->status] }}">{{ ucfirst(str_replace('_', ' ', $stage->status)) }}</span>
                @if ($stage->isOverdue())<span class="badge-od">Overdue</span>@endif
            </div>
        @empty
            <p class="text-sm text-ink-muted">Choose a stage group on the Stages tab.</p>
        @endforelse
    </section>
    <section class="card-pad">
        <h2 class="section-title mb-2">Public website</h2>
        @if (! ($currentTenant?->plan?->hasFeature('public_listings')))
            <p class="text-sm text-ink-muted">Publishing projects to the website is not included in your plan.</p>
        @elseif ($canManage)
            <form method="POST" action="{{ route('app.layouts.publish', $layout) }}" class="flex flex-col gap-3">@csrf
                <input type="hidden" name="is_public" value="0">
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_public" value="1" class="check" @checked($layout->is_public)> Show on the public website when launched</label>
                <div><label for="ps" class="label">Short description for buyers</label><textarea id="ps" name="public_summary" rows="3" maxlength="500" class="input py-2.5">{{ old('public_summary', $layout->public_summary) }}</textarea></div>
                <button class="btn-outline btn-sm">Save</button>
            </form>
            @if ($layout->is_public && $layout->status->value === 'launched')
                <a href="{{ route('projects.show', [$currentTenant->slug, \Illuminate\Support\Str::lower($layout->code)]) }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold">View public page <x-icon name="chevron" class="h-4 w-4" /></a>
            @endif
        @else
            <p class="text-sm">{{ $layout->is_public ? 'Visible on the website' : 'Not published' }}</p>
        @endif
    </section>
    <section class="card-pad">
        <h2 class="section-title mb-3">Notification groups</h2>
        @if ($canManage)
            <form method="POST" action="{{ route('app.layouts.notification-groups', $layout) }}" class="flex flex-col gap-2">@csrf
                @forelse ($notificationGroups as $group)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="groups[]" value="{{ $group->id }}" class="check" @checked($layout->notificationGroups->contains($group))> {{ $group->name }}</label>
                @empty
                    <p class="text-sm text-ink-muted">Create groups in Settings → Notifications.</p>
                @endforelse
                @if ($notificationGroups->isNotEmpty())<button class="btn-outline btn-sm mt-2">Save</button>@endif
            </form>
        @else
            <p class="text-sm">{{ $layout->notificationGroups->pluck('name')->implode(', ') ?: '—' }}</p>
        @endif
    </section>
</div>
