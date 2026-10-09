<x-layouts.workspace :title="$project->exists ? 'Edit project' : 'New project'">
    <x-page-header :title="$project->exists ? 'Edit '.$project->name : 'New project'" subtitle="Step 1 – project details" :back="$project->exists ? route('ws.projects.show', $project) : route('ws.projects.index')" />
    <form method="POST" action="{{ $project->exists ? route('ws.projects.update', $project) : route('ws.projects.store') }}" class="space-y-6">
        @csrf @if ($project->exists) @method('PUT') @endif
        <div class="card card-pad">
            <fieldset>
                <legend class="label">Approval type <span class="text-red-700">*</span></legend>
                <div class="mt-1 grid gap-3 sm:grid-cols-3">
                    @foreach (\App\Models\Project::APPROVAL_TYPES as $t)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-navy-100 p-4 has-[:checked]:border-teal has-[:checked]:bg-teal-50 {{ $project->exists && in_array($project->status, ['in_progress', 'ready_to_launch', 'launched']) ? 'pointer-events-none opacity-60' : '' }}">
                            <input type="radio" name="approval_type" value="{{ $t }}" class="text-teal-700 focus:ring-teal" @checked(old('approval_type', $project->approval_type) === $t)>
                            <span><span class="block font-bold">{{ $t }}</span><span class="text-xs text-muted">{{ ['Village' => 'Panchayat / BDO + DTCP', 'Town' => 'Town panchayat / municipality', 'City' => 'Corporation / CMDA-DTCP'][$t] }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <div class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <h2 class="section-title sm:col-span-2 lg:col-span-3">Details</h2>
            @if ($project->exists)<p class="text-sm sm:col-span-2 lg:col-span-3">Project code <span class="font-mono font-semibold">{{ $project->project_code }}</span></p>@else<p class="text-sm text-muted sm:col-span-2 lg:col-span-3">The project code is generated from your Project ID prefix.</p>@endif
            <x-field name="name" label="Project name" :value="$project->name" required />
            <x-field name="location" label="Location (town / village)" :value="$project->location" required />
            <x-field name="district" label="District" :value="$project->district" required hint="Used to pick the SRO automatically" />
            <x-field name="address" label="Full address" :value="$project->address" class="sm:col-span-2" />
            <x-field name="state" label="State" :value="$project->state ?? 'Tamil Nadu'" required />
            <x-field name="pin" label="PIN code" :value="$project->pin" inputmode="numeric" maxlength="6" />
            <x-field name="email" type="email" label="Project email" :value="$project->email" />
            <x-field name="contact" label="Project contact" :value="$project->contact" inputmode="numeric" maxlength="10" />
            <x-field name="owner_name" label="Land owner" :value="$project->owner_name" />
            <x-select name="manager_id" label="Assigned manager" :options="$managers" :value="$project->manager_id" placeholder="Not assigned" />
            <x-field name="map_url" type="url" label="Location map link" :value="$project->map_url" placeholder="https://maps.google.com/…" />
        </div>
        <div class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <h2 class="section-title sm:col-span-2 lg:col-span-4">Land & value</h2>
            <x-field name="survey_numbers" label="Survey number(s)" :value="$project->survey_numbers" class="sm:col-span-2" />
            <x-field name="patta_numbers" label="Patta number(s)" :value="$project->patta_numbers" class="sm:col-span-2" />
            <div><x-field name="size_acres" id="size_acres" type="number" step="0.0001" label="Size (acres)" :value="$project->size_acres" required />
                <p class="hint">= <span data-calc="size_acres*one" data-calc-number data-calc-factor="43560">0</span> sq ft</p><input type="hidden" id="one" value="1"></div>
            <x-select name="land_classification" label="Land classification" :options="array_combine(\App\Models\Project::LAND_CLASSES, \App\Models\Project::LAND_CLASSES)" :value="$project->land_classification" placeholder="Choose" />
            <div><x-field name="guideline_rate" id="guideline_rate" type="number" step="0.01" label="Guideline value per sq ft (₹)" :value="$project->guideline_rate" />
                <p class="hint">Guideline value: <strong data-calc="size_acres*guideline_rate" data-calc-factor="43560">—</strong></p></div>
            <div><x-field name="market_rate" id="market_rate" type="number" step="0.01" label="Market value per sq ft (₹)" :value="$project->market_rate" />
                <p class="hint">Market value: <strong data-calc="size_acres*market_rate" data-calc-factor="43560">—</strong></p></div>
        </div>
        <button class="btn-primary">{{ $project->exists ? 'Save changes' : 'Create project' }}</button>
    </form>
</x-layouts.workspace>
