<x-field name="doc_code" label="Doc ID" :value="$d->doc_code" id="{{ $p }}code" required />
<x-field name="checklist" label="Checklist" :value="$d->checklist" id="{{ $p }}cl" required list="checklists" />
<x-select name="mandatory" label="Mandatory" :options="['Yes' => 'Yes', 'Conditional' => 'Conditional', 'No' => 'No']" :value="$d->mandatory" id="{{ $p }}m" />
<x-field name="name" label="Document / item" :value="$d->name" id="{{ $p }}name" required class="sm:col-span-3" />
<x-field name="issued_by" label="Issued by / source" :value="$d->issued_by" id="{{ $p }}ib" />
<x-field name="submission_format" label="Submission format" :value="$d->submission_format" id="{{ $p }}sf" />
<x-field name="applies_to" label="Applies to" :value="$d->applies_to" id="{{ $p }}at" required />
<x-field name="remarks" label="Condition / remarks" :value="$d->remarks" id="{{ $p }}rm" class="sm:col-span-3" />
<x-field name="stage_no" type="number" label="Stage no." :value="$d->stage_no" id="{{ $p }}sn" />
<x-field name="village_task_code" label="Village task ID" :value="$d->village_task_code" id="{{ $p }}vt" placeholder="N/A" />
<x-field name="town_task_code" label="Town task ID" :value="$d->town_task_code" id="{{ $p }}tt" placeholder="N/A" />
<x-field name="city_task_code" label="City task ID" :value="$d->city_task_code" id="{{ $p }}ct" placeholder="N/A" />
<div class="flex items-end"><input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active" :checked="$d->is_active ?? true" /></div>
