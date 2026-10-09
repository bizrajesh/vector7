<x-layouts.workspace :title="'Estimate — '.$project->name">
    @php($lines = $estimate?->lines ?? collect())
    @php($facLines = $lines->where('line_type', 'facility')->keyBy('facility_master_id'))
    @php($stageLines = $lines->where('line_type', 'stage')->keyBy('stage_no'))
    @php($otherLines = $lines->where('line_type', 'other')->values())
    <x-page-header :title="'Estimate & budget'" :subtitle="$project->name.' · '.$project->approval_type.' rates · '.\App\Support\Format::num($project->totalSqft()).' sq ft ('.(float) $project->size_acres.' acres)'" :back="route('ws.projects.show', $project)">
        @if ($estimate)@can('estimates.export')<a href="{{ route('ws.estimate.export', [$project, 'pdf']) }}" class="btn-light">PDF</a><a href="{{ route('ws.estimate.export', [$project, 'xlsx']) }}" class="btn-light">Excel</a>@endcan @endif
    </x-page-header>
    @if ($locked)<div class="flash-warn mb-4">The estimate is read-only{{ in_array($project->status, ['in_progress', 'ready_to_launch', 'launched']) ? ' because the project has started' : '' }}.</div>@endif

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-sm font-semibold">Facility tier:</span>
        @foreach (['Basic', 'Standard', 'Premium'] as $t)
            <a href="{{ route('ws.estimate.edit', ['project' => $project, 'tier' => $t]) }}" class="{{ $tier === $t ? 'btn-primary' : 'btn-light' }} btn-sm">{{ $t }}</a>
        @endforeach
        <span class="text-xs text-muted">Y = included (ticked) · Opt = optional (tick to add)</span>
    </div>

    <form method="POST" action="{{ route('ws.estimate.save', $project) }}" data-estimate data-sqft="{{ $project->totalSqft() }}" data-actual-sellable="{{ $actualSellable }}" class="grid gap-6 xl:grid-cols-3">
        @csrf
        <input type="hidden" name="tier" value="{{ $tier }}">
        <fieldset class="min-w-0 space-y-6 xl:col-span-2" @disabled($locked)>
            <div class="card">
                <h2 class="section-title p-4">Facilities ({{ $tier }})</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th class="w-8"><span class="sr-only">Include</span></th><th>Facility</th><th class="w-28">Quantity</th><th>Unit</th><th class="w-36">Unit cost ₹</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                    @foreach ($facilities as $f)
                        @php($line = $facLines[$f->id] ?? null)
                        @php($flag = $f->tierFlag($tier))
                        <tr data-line="facility">
                            <td><input type="checkbox" class="checkbox" name="facility[{{ $f->id }}][include]" value="1" data-inc @checked($line || (! $estimate && $flag === 'Y')) aria-label="Include {{ $f->name }}"></td>
                            <td><p class="font-semibold">{{ $f->name }} @if ($flag === 'Opt')<span class="badge-gray">Optional</span>@endif @if ($f->is_statutory)<span class="badge-navy">Statutory</span>@endif</p><p class="text-xs text-muted">{{ $f->category }} · {{ $f->specification }}</p></td>
                            <td><input type="number" step="0.01" min="0" class="input" name="facility[{{ $f->id }}][qty]" value="{{ $line ? (float) $line->quantity : 0 }}" data-qty aria-label="Quantity"></td>
                            <td class="text-sm">{{ $f->unit }}</td>
                            <td><input type="number" step="0.01" min="0" class="input" name="facility[{{ $f->id }}][cost]" value="{{ $line ? (float) $line->unit_cost : $f->costFor($project->approval_type) }}" data-cost aria-label="Unit cost"></td>
                            <td class="num" data-amount>₹0</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>

            <div class="card">
                <h2 class="section-title p-4">Approval stages ({{ $project->approval_type }})</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th class="w-8"></th><th>Stage</th><th class="num">Sub-tasks</th><th class="num">Days</th><th class="w-40">Cost ₹ (fees, consultants)</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                    @foreach ($stages as $s)
                        @php($line = $stageLines[$s->stage_no] ?? null)
                        <tr data-line="stage">
                            <td><input type="hidden" name="stage[{{ $s->stage_no }}][include]" value="0"><input type="checkbox" class="checkbox" name="stage[{{ $s->stage_no }}][include]" value="1" data-inc @checked($line || ! $estimate) aria-label="Include stage {{ $s->stage_no }}"></td>
                            <td><input type="hidden" name="stage[{{ $s->stage_no }}][name]" value="{{ $s->name }}"><span class="font-semibold">{{ $s->stage_no }}. {{ $s->name }}</span></td>
                            <td class="num">{{ $s->subtasks->count() }}</td>
                            <td class="num">{{ $s->subtasks->sum('default_duration_days') }}</td>
                            <td><input type="hidden" value="1" data-qty><input type="number" step="0.01" min="0" class="input" name="stage[{{ $s->stage_no }}][cost]" value="{{ $line ? (float) $line->amount : (float) $s->default_cost + (float) $s->subtasks->sum('default_cost') }}" data-cost aria-label="Stage cost"></td>
                            <td class="num" data-amount>₹0</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>

            <div class="card" data-repeater>
                <h2 class="section-title p-4">Other costs</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Description</th><th class="w-28">Qty</th><th class="w-28">Unit</th><th class="w-36">Unit cost ₹</th><th class="num">Amount</th><th></th></tr></thead>
                    <tbody data-repeater-body>
                    @foreach ($otherLines->isEmpty() ? collect([['description' => 'Land conversion / registration overheads', 'quantity' => 1, 'unit' => 'lump sum', 'unit_cost' => 0]]) : $otherLines as $k => $o)
                        <tr data-line="other" data-repeater-row>
                            <td><input class="input" name="other[{{ $k }}][description]" value="{{ data_get($o, 'description') }}" aria-label="Description"></td>
                            <td><input type="number" step="0.01" class="input" name="other[{{ $k }}][qty]" value="{{ (float) data_get($o, 'quantity') }}" data-qty aria-label="Quantity"></td>
                            <td><input class="input" name="other[{{ $k }}][unit]" value="{{ data_get($o, 'unit') }}" aria-label="Unit"></td>
                            <td><input type="number" step="0.01" class="input" name="other[{{ $k }}][cost]" value="{{ (float) data_get($o, 'unit_cost') }}" data-cost aria-label="Unit cost"></td>
                            <td class="num" data-amount>₹0</td>
                            <td><button type="button" class="btn-ghost btn-sm" data-repeater-remove aria-label="Remove">✕</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <template><tr data-line="other" data-repeater-row>
                    <td><input class="input" name="other[__i__][description]" aria-label="Description"></td>
                    <td><input type="number" step="0.01" class="input" name="other[__i__][qty]" value="1" data-qty aria-label="Quantity"></td>
                    <td><input class="input" name="other[__i__][unit]" value="lump sum" aria-label="Unit"></td>
                    <td><input type="number" step="0.01" class="input" name="other[__i__][cost]" value="0" data-cost aria-label="Unit cost"></td>
                    <td class="num" data-amount>₹0</td>
                    <td><button type="button" class="btn-ghost btn-sm" data-repeater-remove aria-label="Remove">✕</button></td>
                </tr></template>
                <div class="p-4"><button type="button" class="btn-light btn-sm" data-repeater-add><x-icon name="plus" class="h-4 w-4" /> Add cost</button></div>
            </div>
        </fieldset>

        <aside class="xl:sticky xl:top-20 xl:self-start">
            <div class="card card-pad space-y-3">
                <h2 class="section-title">Summary</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Facilities</dt><dd data-total="facility">₹0</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Approval stages</dt><dd data-total="stage">₹0</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Other</dt><dd data-total="other">₹0</dd></div>
                    <div class="flex justify-between border-t border-navy-50 pt-2 text-base font-extrabold"><dt>Total production cost</dt><dd data-total="total">₹0</dd></div>
                </dl>
                <fieldset class="grid grid-cols-2 gap-3" @disabled($locked)>
                    <x-field name="sellable_pct" type="number" step="0.01" label="Sellable %" :value="$estimate->sellable_pct ?? $settings->sellable_pct" required />
                    <x-field name="mrp_multiplier" type="number" step="0.01" label="MRP ×" :value="$estimate->mrp_multiplier ?? $settings->mrp_multiplier" required />
                </fieldset>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Sellable area {{ $actualSellable > 0 ? '(actual plots)' : '' }}</dt><dd data-total="sellable">—</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Cost per sellable sq ft</dt><dd data-total="per_sqft">—</dd></div>
                    <div class="flex justify-between text-base font-extrabold text-teal-700"><dt>MRP per sq ft</dt><dd data-total="mrp">—</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Estimated duration</dt><dd>{{ $duration }} working days</dd></div>
                </dl>
                <p class="text-xs text-muted">Duration follows the critical path through sub-task dependencies.</p>
                @unless ($locked)<button class="btn-primary w-full">Save estimate</button>@endunless
            </div>
        </aside>
    </form>
</x-layouts.workspace>
