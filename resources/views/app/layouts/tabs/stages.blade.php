@if ($editable && $canManage)
    <form method="POST" action="{{ route('app.layouts.stage-group', $layout) }}" class="card-pad mb-4 flex flex-wrap items-end gap-3" data-confirm="Copy stages from this group? Existing project stages will be replaced.">@csrf
        <x-select name="stage_group_id" label="Stage group" :options="$stageGroups->pluck('name', 'id')" :value="$layout->stage_group_id" placeholder="Choose a stage group" class="min-w-[240px] flex-1" required />
        <button class="btn-primary">{{ $layout->stages->isEmpty() ? 'Apply group' : 'Re-apply group' }}</button>
    </form>
@endif

<div class="flex flex-col gap-3">
    @forelse ($layout->stages as $stage)
        @php
            $actual = $stage->actualCost();
            $budget = (float) $stage->budget_cost;
            $pct = $budget > 0 ? round($actual / $budget * 100) : 0;
            $statusCss = ['pending' => 'badge-rs', 'in_progress' => 'badge-os', 'completed' => 'badge-av', 'skipped' => 'badge-bk'][$stage->status];
        @endphp
        <details class="card group" @if ($stage->status === 'in_progress') open @endif>
            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3.5">
                <span class="num w-12 text-xs font-bold text-ink-muted">{{ $stage->stage_no }}</span>
                <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $stage->name }}</span>
                    <span class="text-xs text-ink-muted">{{ $stage->planned_start?->format('j M') }} → {{ $stage->planned_end?->format('j M Y') }} · {{ $stage->duration_days }}d · {{ $stage->owner?->name ?? 'No owner' }}@if ($stage->dependsOn->isNotEmpty()) · after {{ $stage->dependsOn->pluck('name')->implode(', ') }}@endif</span></span>
                <span class="num text-xs {{ $pct >= 100 ? 'font-semibold text-[#A12622]' : 'text-ink-muted' }}">@inrShort($actual) / @inrShort($budget)</span>
                <span class="{{ $statusCss }}">{{ ucfirst(str_replace('_', ' ', $stage->status)) }}</span>
                @if ($stage->isOverdue())<span class="badge-od">Overdue</span>@endif
                @unless ($stage->is_mandatory)<span class="badge-bk">Optional</span>@endunless
            </summary>
            <div class="grid gap-5 border-t border-line-soft p-4 lg:grid-cols-2">
                <div>
                    <div class="mb-3 flex items-center gap-2 text-xs text-ink-muted"><span class="progress flex-1"><span style="width: {{ (float) $stage->progress_pct }}%"></span></span>{{ (float) $stage->progress_pct }}% done</div>
                    @foreach ($stage->tasks as $task)
                        <form method="POST" action="{{ route('app.tasks.toggle', $task) }}" class="flex items-center gap-3 border-t border-line-soft py-2 text-sm first:border-t-0">@csrf
                            <input type="hidden" name="done" value="{{ $task->is_done ? 0 : 1 }}">
                            <button type="submit" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border {{ $task->is_done ? 'border-teal bg-teal text-white' : 'border-[#D6D2BD] bg-white' }}" @disabled($stage->status !== 'in_progress' || ! $canManage) aria-label="{{ $task->is_done ? 'Mark not done' : 'Mark done' }}: {{ $task->name }}">@if ($task->is_done)<x-icon name="check" class="h-4 w-4" stroke="3" />@endif</button>
                            <span class="flex-1 {{ $task->is_done ? 'text-ink-muted line-through' : '' }}">{{ $task->name }}</span>
                            <span class="num text-xs text-ink-muted">{{ (float) $task->effort_days }}d · {{ (float) $task->weight_pct }}%</span>
                        </form>
                    @endforeach
                    @if ($canManage)
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($stage->status === 'pending' && ! $editable)
                                <form method="POST" action="{{ route('app.stages.start', $stage) }}">@csrf<button class="btn-primary btn-sm">Start stage</button></form>
                                @unless ($stage->is_mandatory)
                                    <form method="POST" action="{{ route('app.stages.skip', $stage) }}" class="flex gap-2">@csrf<input name="reason" class="input min-h-[36px] w-40 py-1 text-sm" placeholder="Reason to skip" required aria-label="Reason to skip"><button class="btn-ghost btn-sm">Skip</button></form>
                                @endunless
                            @elseif ($stage->status === 'in_progress')
                                <form method="POST" action="{{ route('app.stages.complete', $stage) }}" data-confirm="Mark {{ $stage->name }} as completed?">@csrf<button class="btn-primary btn-sm">Complete stage</button></form>
                            @endif
                        </div>
                    @endif
                </div>
                @if ($canManage)
                    <div class="flex flex-col gap-4">
                        @if ($stage->status === 'pending')
                            <form method="POST" action="{{ route('app.stages.update', $stage) }}" class="grid grid-cols-2 gap-2">@csrf @method('PUT')
                                <x-field name="budget_cost" type="number" step="0.01" label="Budget (₹)" :value="$stage->budget_cost" required />
                                <x-field name="duration_days" type="number" label="Duration (days)" :value="$stage->duration_days" required />
                                <x-select name="owner_id" label="Owner" :options="$owners->pluck('name', 'id')" :value="$stage->owner_id" placeholder="Unassigned" class="col-span-2" />
                                <button class="btn-ghost btn-sm col-span-2">Save estimate</button>
                            </form>
                        @endif
                        @if ($editable)
                            <form method="POST" action="{{ route('app.stages.dependencies', $stage) }}">@csrf
                                <p class="label">Depends on</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($layout->stages->where('id', '!=', $stage->id) as $other)
                                        <label class="pill cursor-pointer"><input type="checkbox" name="depends_on[]" value="{{ $other->id }}" class="check mr-1.5 h-4 w-4" @checked($stage->dependsOn->contains('id', $other->id))>{{ $other->stage_no }}</label>
                                    @endforeach
                                </div>
                                <button class="btn-ghost btn-sm mt-2">Save links</button>
                            </form>
                        @endif
                        @if (in_array($stage->status, ['in_progress', 'completed']) && auth()->user()->can('expenses.record'))
                            <form method="POST" action="{{ route('app.stages.expenses', $stage) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2 rounded-xl bg-cream-100 p-3">@csrf
                                <p class="col-span-2 text-sm font-bold">Record expense</p>
                                <x-field name="amount" type="number" step="0.01" label="Amount (₹)" required inputmode="decimal" />
                                <x-field name="entry_date" type="date" label="Date" :value="now()->toDateString()" required />
                                <x-field name="description" label="Description" required class="col-span-2" />
                                <x-field name="party" label="Paid to" />
                                <x-select name="mode" label="Mode" :options="['cash' => 'Cash', 'upi' => 'UPI', 'neft' => 'NEFT', 'cheque' => 'Cheque', 'card' => 'Card']" />
                                <div class="col-span-2"><label class="label" for="att-{{ $stage->id }}">Bill / receipt</label><input id="att-{{ $stage->id }}" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" class="input py-2.5"></div>
                                <button class="btn-primary btn-sm col-span-2">Save expense</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </details>
    @empty
        <div class="card"><x-empty title="No stages yet" icon="ops">Apply a stage group above to copy its stages and tasks into this project.</x-empty></div>
    @endforelse
</div>
