<x-field name="facility_code" label="Facility ID" :value="$f->facility_code" id="{{ $p }}code" required />
<x-field name="category" label="Category" :value="$f->category" id="{{ $p }}cat" required />
<x-field name="name" label="Facility" :value="$f->name" id="{{ $p }}name" required />
<x-field name="specification" label="Specification" :value="$f->specification" id="{{ $p }}spec" class="sm:col-span-2" />
<x-field name="unit" label="Unit" :value="$f->unit" id="{{ $p }}unit" required />
@foreach (['tier_basic' => 'Basic', 'tier_standard' => 'Standard', 'tier_premium' => 'Premium'] as $k => $l)
    <x-select :name="$k" :label="$l" :options="['Y' => 'Y — included', 'Opt' => 'Opt — optional', '-' => '- — not included']" :value="$f->$k" id="{{ $p }}{{ $k }}" />
@endforeach
<x-field name="cost_village" type="number" step="0.01" label="Village unit cost ₹" :value="$f->cost_village" id="{{ $p }}cv" required />
<x-field name="cost_town" type="number" step="0.01" label="Town unit cost ₹" :value="$f->cost_town" id="{{ $p }}ct" required />
<x-field name="cost_city" type="number" step="0.01" label="City unit cost ₹" :value="$f->cost_city" id="{{ $p }}cc" required />
<div class="flex gap-4 sm:col-span-3">
    <input type="hidden" name="is_statutory" value="0"><x-checkbox name="is_statutory" label="Statutory" :checked="$f->is_statutory" />
    <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active" :checked="$f->is_active ?? true" />
</div>
