<x-field name="code" label="Code" :value="$c->code" required id="{{ $p }}code" class="col-span-2" />
<x-select name="discount_type" label="Type" :options="['percent' => '% of price', 'flat' => 'Flat ₹']" :value="$c->discount_type" id="{{ $p }}type" />
<x-field name="value" type="number" step="0.01" label="Value" :value="$c->value" required id="{{ $p }}val" />
<x-field name="valid_from" type="date" label="Valid from" :value="$c->valid_from?->toDateString()" required id="{{ $p }}from" />
<x-field name="valid_to" type="date" label="Valid to" :value="$c->valid_to?->toDateString()" required id="{{ $p }}to" />
<x-field name="max_uses" type="number" label="Max uses (0 = no limit)" :value="$c->max_uses ?? 0" id="{{ $p }}max" class="col-span-2" />
<fieldset class="col-span-2"><legend class="label">Projects (none = all)</legend>
    @foreach (\App\Models\Project::launched()->orderBy('name')->get() as $proj)<x-checkbox name="project_ids[]" :value="$proj->id" :label="$proj->name" :checked="in_array($proj->id, $c->project_ids ?? [])" />@endforeach
</fieldset>
