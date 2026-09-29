<x-layouts.app :title="$group->name">
    <x-page-header :title="$group->name" :subtitle="$group->stages->count().' stages · total '.\App\Support\Money::inr($group->stages->sum('cost')).' · '.$group->stages->sum('duration_days').' days if sequential'" :back="route('app.settings.stage-groups.index')" />

    <div class="flex flex-col gap-3">
        @foreach ($group->stages as $stage)
            <details class="card group" @if ($errors->any() && old('_stage') == $stage->id) open @endif>
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 px-4 py-3.5">
                    <span class="num w-14 text-xs font-bold text-ink-muted">{{ $stage->stage_no }}</span>
                    <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $stage->seq_no }}. {{ $stage->name }}</span>
                        <span class="text-xs text-ink-muted">@inr($stage->cost) · {{ $stage->duration_days }} days · {{ $stage->tasks->count() }} tasks @if ($stage->dependsOn->isNotEmpty()) · after {{ $stage->dependsOn->pluck('stage_no')->implode(', ') }}@endif</span></span>
                    <span class="{{ $stage->stage_type === 'dependent' ? 'badge-os' : 'badge-rs' }}">{{ ucfirst($stage->stage_type) }}</span>
                    <span class="{{ $stage->is_mandatory ? 'badge-av' : 'badge-bk' }}">{{ $stage->is_mandatory ? 'Mandatory' : 'Optional' }}</span>
                    <x-icon name="down" class="h-4 w-4 transition group-open:rotate-180" />
                </summary>
                <div class="grid gap-5 border-t border-line-soft p-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('app.settings.stage-groups.stages.update', [$group, $stage]) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <input type="hidden" name="_stage" value="{{ $stage->id }}">
                        <x-field name="name" label="Stage name" :value="$stage->name" required class="sm:col-span-2" />
                        <x-field name="seq_no" type="number" label="Sequence" :value="$stage->seq_no" required />
                        <x-field name="cost" type="number" step="0.01" label="Cost (₹)" :value="$stage->cost" required />
                        <x-field name="duration_days" type="number" label="Duration (days)" :value="$stage->duration_days" required />
                        <x-select name="is_mandatory" label="Mandatory" :options="['1' => 'Mandatory', '0' => 'Optional']" :value="(int) $stage->is_mandatory" />
                        <x-field name="description" label="Description" :value="$stage->description" class="sm:col-span-2" />
                        <fieldset class="sm:col-span-2">
                            <legend class="label">Depends on (linked stages)</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($group->stages->where('id', '!=', $stage->id) as $other)
                                    <label class="pill cursor-pointer"><input type="checkbox" name="depends_on[]" value="{{ $other->id }}" class="check mr-1.5 h-4 w-4" @checked($stage->dependsOn->contains($other))>{{ $other->stage_no }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="flex gap-2 sm:col-span-2"><button type="submit" class="btn-primary btn-sm">Save stage</button></div>
                    </form>
                    <div>
                        <h3 class="mb-2 text-sm font-bold">Tasks <span class="font-normal text-ink-muted">· effort {{ (float) $stage->tasks->sum('effort_days') }} of {{ $stage->duration_days }} days · weight {{ (float) $stage->tasks->sum('weight_pct') }}%</span></h3>
                        @foreach ($stage->tasks as $task)
                            <div class="flex items-center gap-2 border-t border-line-soft py-2 text-sm first:border-t-0">
                                <span class="num w-12 text-xs text-ink-muted">{{ $task->task_no }}</span>
                                <span class="flex-1">{{ $task->name }}</span>
                                <span class="num text-xs text-ink-muted">{{ (float) $task->effort_days }}d · {{ (float) $task->weight_pct }}%</span>
                                <form method="POST" action="{{ route('app.settings.stage-groups.tasks.destroy', [$group, $task]) }}" data-confirm="Remove this task?">@csrf @method('DELETE')
                                    <button class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-700" aria-label="Remove task {{ $task->name }}"><x-icon name="trash" class="h-4 w-4" /></button>
                                </form>
                            </div>
                        @endforeach
                        <form method="POST" action="{{ route('app.settings.stage-groups.tasks.store', [$group, $stage]) }}" class="mt-3 grid grid-cols-2 gap-2">
                            @csrf
                            <x-field name="name" label="Task name" required class="col-span-2" />
                            <x-field name="effort_days" type="number" step="0.25" label="Effort (days)" required />
                            <x-field name="weight_pct" type="number" step="0.01" label="Stage %" required />
                            <button type="submit" class="btn-outline btn-sm col-span-2">Add task</button>
                        </form>
                        <form method="POST" action="{{ route('app.settings.stage-groups.stages.destroy', [$group, $stage]) }}" class="mt-4" data-confirm="Remove stage {{ $stage->name }}?">@csrf @method('DELETE')
                            <button class="btn-danger btn-sm">Remove stage</button>
                        </form>
                    </div>
                </div>
            </details>
        @endforeach
    </div>

    <form method="POST" action="{{ route('app.settings.stage-groups.stages.store', $group) }}" class="card-pad mt-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @csrf
        <h2 class="section-title sm:col-span-3 lg:col-span-6">Add stage</h2>
        <x-field name="seq_no" type="number" label="Sequence" :value="$group->stages->max('seq_no') + 1" required />
        <x-field name="name" label="Stage name" required class="sm:col-span-2" />
        <x-field name="cost" type="number" step="0.01" label="Cost (₹)" required />
        <x-field name="duration_days" type="number" label="Duration (days)" required />
        <x-select name="is_mandatory" label="Mandatory" :options="['1' => 'Mandatory', '0' => 'Optional']" value="1" />
        <div class="sm:col-span-3 lg:col-span-6"><button type="submit" class="btn-primary">Add stage</button></div>
    </form>
</x-layouts.app>
