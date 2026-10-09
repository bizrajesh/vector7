@php($p = $p ?? 'n')
<x-select :id="$p.'_project'" name="project_id" label="Project" :options="$projects->pluck('name', 'id')" :value="$e->project_id" placeholder="General (no project)" />
<x-select :id="$p.'_cat'" name="expense_category_id" label="Category" :options="$categories->pluck('name', 'id')" :value="$e->expense_category_id" required placeholder="Choose" />
<div>
    <label for="{{ $p }}_stage" class="label">Approval stage</label>
    <select id="{{ $p }}_stage" name="project_stage_id" class="input" data-depends="project_id">
        <option value="">Not linked</option>
        @foreach ($stages as $pid => $list)@foreach ($list as $s)<option value="{{ $s->id }}" data-group="{{ $pid }}" @selected((string) old('project_stage_id', $e->project_stage_id) === (string) $s->id)>{{ $s->stage_no }}. {{ $s->name }}</option>@endforeach @endforeach
    </select>
</div>
<div>
    <label for="{{ $p }}_line" class="label">Facility</label>
    <select id="{{ $p }}_line" name="estimate_line_id" class="input" data-depends="project_id">
        <option value="">Not linked</option>
        @foreach ($lines as $pid => $list)@foreach ($list as $l)<option value="{{ $l->id }}" data-group="{{ $pid }}" @selected((string) old('estimate_line_id', $e->estimate_line_id) === (string) $l->id)>{{ $l->description }}</option>@endforeach @endforeach
    </select>
</div>
<x-field :id="$p.'_desc'" name="description" label="Description" :value="$e->description" required class="sm:col-span-2" />
<x-field :id="$p.'_vendor'" name="vendor" label="Paid to (vendor)" :value="$e->vendor" />
<x-field :id="$p.'_amt'" name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" label="Amount (₹)" :value="$e->amount" required />
<x-field :id="$p.'_date'" name="spent_on" type="date" label="Date" :value="optional($e->spent_on)->format('Y-m-d') ?? today()->format('Y-m-d')" :max="today()->format('Y-m-d')" required />
<x-select :id="$p.'_mode'" name="mode" label="Paid by" :options="\App\Models\Payment::MODES" :value="$e->mode" placeholder="—" />
<x-field :id="$p.'_ref'" name="reference_no" label="Reference / cheque no." :value="$e->reference_no" />
<div>
    <label for="{{ $p }}_bill" class="label">Bill (PDF or photo, max 10 MB)</label>
    <input id="{{ $p }}_bill" name="bill" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="input">
</div>
