{{-- Shared registration details. $sale (plot, project, customer), $r (Registration|null), $sros, $sro, $tenant, $checklist --}}
@php
    $seller = $r?->parties->firstWhere('party_type', 'seller');
    $buyer = $r?->parties->firstWhere('party_type', 'buyer');
    $w = $r ? $r->witnesses->values() : collect();
    $plot = $sale->plot;
    $viewer = auth()->user();
@endphp
<div class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <h2 class="section-title sm:col-span-2 lg:col-span-4">Registration</h2>
    <x-field name="registration_date" type="date" label="Registration date" :value="$r?->registration_date?->toDateString() ?? today()->addDays(3)->toDateString()" required />
    <div class="sm:col-span-2">
        <label for="f_sro_id" class="label">Sub-Registrar Office <span class="text-red-700">*</span></label>
        <select id="f_sro_id" name="sro_id" class="input" required>
            @foreach ($sros->groupBy('district') as $district => $list)
                <optgroup label="{{ $district }}">@foreach ($list as $s)<option value="{{ $s->id }}" @selected((int) old('sro_id', $r?->sro_id ?? $sro?->id) === $s->id)>{{ $s->name }}{{ $s->taluk ? ' ('.$s->taluk.')' : '' }}</option>@endforeach</optgroup>
            @endforeach
        </select>
        <p class="hint">Picked automatically from the project district ({{ $sale->project->district }}).</p>
    </div>
    <x-field name="document_writer" label="Document writer" :value="$r?->document_writer" />
</div>

<div class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <h2 class="section-title sm:col-span-2 lg:col-span-4">Plot (asset) details</h2>
    <dl class="grid grid-cols-2 gap-3 text-sm sm:col-span-2 lg:col-span-4 lg:grid-cols-6">
        <div><dt class="text-muted">Layout</dt><dd class="font-semibold">{{ $sale->project->name }}</dd></div>
        <div><dt class="text-muted">Plot no.</dt><dd class="font-semibold">{{ $plot->plot_no }}</dd></div>
        <div><dt class="text-muted">Size</dt><dd class="font-semibold">{{ \App\Support\Format::num($plot->size_sqft) }} sq ft</dd></div>
        <div><dt class="text-muted">Survey no.</dt><dd class="font-semibold">{{ $sale->project->survey_numbers ?? '—' }}</dd></div>
        <div><dt class="text-muted">Patta no.</dt><dd class="font-semibold">{{ $plot->patta_number }}</dd></div>
        <div><dt class="text-muted">Location</dt><dd class="font-semibold">{{ $sale->project->location }}, {{ $sale->project->district }}</dd></div>
    </dl>
    <x-field name="pr_numbers" label="PR numbers" :value="$r?->pr_numbers" class="sm:col-span-2" />
    <x-field name="length_ft" type="number" step="0.01" label="Length (ft)" :value="$plot->length_ft ? (float) $plot->length_ft : ''" />
    <x-field name="width_ft" type="number" step="0.01" label="Width (ft)" :value="$plot->width_ft ? (float) $plot->width_ft : ''" />
    <x-field name="east_boundary" label="East boundary" :value="$plot->east_boundary" />
    <x-field name="west_boundary" label="West boundary" :value="$plot->west_boundary" />
    <x-field name="north_boundary" label="North boundary" :value="$plot->north_boundary" />
    <x-field name="south_boundary" label="South boundary" :value="$plot->south_boundary" />
</div>

<div class="grid gap-6 lg:grid-cols-2">
    @foreach (['seller' => ['Owner (seller)', $seller, $tenant->name, $tenant->addressLine(), $tenant->contact], 'buyer' => ['Buyer', $buyer, $sale->customer->name, trim(collect([$sale->customer->address, $sale->customer->city, $sale->customer->district, $sale->customer->pin])->filter()->implode(', ')), $sale->customer->mobile]] as $t => [$title, $party, $defName, $defAddr, $defMobile])
        <div class="card card-pad grid gap-3 sm:grid-cols-2">
            <h2 class="section-title sm:col-span-2">{{ $title }}</h2>
            <x-field :name="$t.'_name'" label="Name" :value="$party?->name ?? $defName" required class="sm:col-span-2" />
            <x-field :name="$t.'_relation'" label="S/o, D/o, W/o" :value="$party?->relation_name" />
            <x-field :name="$t.'_age'" type="number" label="Age" :value="$party?->age" />
            <x-textarea :name="$t.'_address'" label="Address" :value="$party?->address ?? $defAddr" rows="2" required class="sm:col-span-2" />
            <x-field :name="$t.'_mobile'" label="Mobile" :value="$party?->mobile ?? $defMobile" inputmode="numeric" maxlength="10" />
            <x-field :name="$t.'_pan'" label="PAN" :value="$party ? $party->panFor($viewer) : ''" maxlength="10" :hint="$party && ! $viewer->isAdmin() ? 'Shown masked — re-enter to change' : 'Stored encrypted'" class="uppercase" />
        </div>
    @endforeach
</div>

<div class="card card-pad grid gap-4 lg:grid-cols-2">
    <h2 class="section-title lg:col-span-2">Witnesses</h2>
    @foreach ([0, 1] as $i)
        <div class="grid gap-3 rounded-xl bg-page p-4 sm:grid-cols-2">
            <p class="font-semibold sm:col-span-2">Witness {{ $i + 1 }}</p>
            <x-field name="w[{{ $i }}][name]" label="Name" :value="$w[$i]->name ?? ''" required id="w{{ $i }}n" />
            <x-field name="w[{{ $i }}][relation_name]" label="S/o, D/o, W/o" :value="$w[$i]->relation_name ?? ''" id="w{{ $i }}r" />
            <x-field name="w[{{ $i }}][age]" type="number" label="Age" :value="$w[$i]->age ?? ''" id="w{{ $i }}a" />
            <x-field name="w[{{ $i }}][address]" label="Address" :value="$w[$i]->address ?? ''" required id="w{{ $i }}d" />
        </div>
    @endforeach
</div>
