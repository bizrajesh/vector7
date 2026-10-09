<?php

namespace App\Services;

use App\Models\DocumentChecklist;
use App\Models\FacilityMaster;
use App\Models\Sro;
use App\Models\StageMaster;
use App\Models\SubtaskMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for prerequisite masters, shared by the App workspace (tenant_id NULL = templates)
 * and the tenant workspace (tenant's own editable copies).
 * Stage → Sub-task (dependencies, durations) → Document checklist links.
 */
class MasterService
{
    public const TYPES = ['stages' => 'Stages & sub-tasks', 'facilities' => 'Facilities', 'documents' => 'Document checklist', 'sros' => 'SRO lookup'];

    public static function data(string $type, ?int $tid, Request $request): array
    {
        $area = in_array($request->query('area'), ['Village', 'Town', 'City'], true) ? $request->query('area') : 'Village';

        return match ($type) {
            'stages' => [
                'area' => $area,
                'stages' => StageMaster::withoutGlobalScopes()->where('tenant_id', $tid)->where('area_type', $area)->orderBy('stage_no')
                    ->with(['subtasks' => fn ($q) => $q->withoutGlobalScopes()->with(['dependencies' => fn ($d) => $d->withoutGlobalScopes(), 'documents' => fn ($d) => $d->withoutGlobalScopes()])])->get(),
            ],
            'facilities' => ['facilities' => FacilityMaster::withoutGlobalScopes()->where('tenant_id', $tid)->orderBy('sino')->get()],
            'documents' => ['documents' => DocumentChecklist::withoutGlobalScopes()->where('tenant_id', $tid)
                ->when($request->query('checklist'), fn ($q, $c) => $q->where('checklist', $c))->orderBy('sino')->get(),
                'checklists' => DocumentChecklist::withoutGlobalScopes()->where('tenant_id', $tid)->distinct()->orderBy('checklist')->pluck('checklist')],
            'sros' => ['sros' => Sro::orderBy('district')->orderBy('name')->get()],
        };
    }

    public static function save(string $type, ?int $tid, Request $request, ?int $id = null): void
    {
        match ($type) {
            'stages' => $request->input('kind') === 'stage' ? self::saveStage($tid, $request, $id) : self::saveSubtask($tid, $request, $id),
            'facilities' => self::saveFacility($tid, $request, $id),
            'documents' => self::saveDocument($tid, $request, $id),
            'sros' => self::saveSro($request, $id),
        };
    }

    public static function delete(string $type, ?int $tid, Request $request, int $id): void
    {
        $q = fn ($m) => $m::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($id);
        match ($type) {
            'stages' => $request->input('kind') === 'stage' ? $q(StageMaster::class)->delete() : $q(SubtaskMaster::class)->delete(),
            'facilities' => $q(FacilityMaster::class)->delete(),
            'documents' => $q(DocumentChecklist::class)->delete(),
            'sros' => Sro::findOrFail($id)->delete(),
        };
    }

    private static function saveStage(?int $tid, Request $r, ?int $id): void
    {
        $data = $r->validate([
            'area_type' => 'required|in:Village,Town,City',
            'stage_no' => ['required', 'integer', 'min:1', 'max:99', Rule::unique('stage_masters')->where('tenant_id', $tid)->where('area_type', $r->input('area_type'))->ignore($id)],
            'name' => 'required|string|max:120',
            'default_cost' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $data['default_cost'] = $data['default_cost'] ?? 0;
        $data['is_active'] = $r->boolean('is_active', true);
        $id ? StageMaster::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($id)->update($data)
            : StageMaster::create($data + ['tenant_id' => $tid]);
    }

    private static function saveSubtask(?int $tid, Request $r, ?int $id): void
    {
        $data = $r->validate([
            'stage_master_id' => 'required|integer',
            'task_code' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9.\-]+$/', Rule::unique('subtask_masters')->where('tenant_id', $tid)->ignore($id)],
            'short_name' => 'required|string|max:60',
            'name' => 'required|string|max:255',
            'responsible' => 'nullable|string|max:150',
            'output' => 'nullable|string|max:150',
            'default_duration_days' => 'required|integer|min:0|max:999',
            'default_cost' => 'nullable|numeric|min:0',
            'depends_on' => 'nullable|string|max:255',
            'doc_codes' => 'nullable|string|max:1000',
        ]);
        $stage = StageMaster::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($data['stage_master_id']);
        $codes = fn ($s) => array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $s))));
        $deps = $codes($data['depends_on'] ?? '');
        $docs = $codes($data['doc_codes'] ?? '');

        DB::transaction(function () use ($tid, $id, $data, $stage, $deps, $docs, $r) {
            $attrs = collect($data)->except(['depends_on', 'doc_codes'])->all();
            $attrs['default_cost'] = $attrs['default_cost'] ?? 0;
            $attrs['is_active'] = $r->boolean('is_active', true);
            $task = $id ? tap(SubtaskMaster::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($id))->update($attrs) : SubtaskMaster::create($attrs + ['tenant_id' => $tid]);

            $depIds = [];
            foreach ($deps as $code) {
                $d = SubtaskMaster::withoutGlobalScopes()->where('tenant_id', $tid)->where('task_code', $code)->first();
                if (! $d || $d->id === $task->id) {
                    throw ValidationException::withMessages(['depends_on' => "Unknown task ID $code."]);
                }
                if ($d->stage()->withoutGlobalScopes()->first()->area_type !== $stage->area_type) {
                    throw ValidationException::withMessages(['depends_on' => "$code belongs to another area type."]);
                }
                $depIds[] = $d->id;
            }
            $task->dependencies()->sync($depIds);
            self::assertNoCycle($tid, $stage->area_type);

            $docIds = [];
            foreach ($docs as $code) {
                $doc = DocumentChecklist::withoutGlobalScopes()->where('tenant_id', $tid)->where('doc_code', $code)->first();
                if (! $doc) {
                    throw ValidationException::withMessages(['doc_codes' => "Unknown document ID $code."]);
                }
                $docIds[] = $doc->id;
            }
            $task->documents()->sync($docIds);
        });
    }

    public static function assertNoCycle(?int $tid, string $area): void
    {
        $tasks = SubtaskMaster::withoutGlobalScopes()->where('tenant_id', $tid)
            ->whereHas('stage', fn ($q) => $q->withoutGlobalScopes()->where('area_type', $area))
            ->with(['dependencies' => fn ($q) => $q->withoutGlobalScopes()])->get();
        $graph = $tasks->mapWithKeys(fn ($t) => [$t->task_code => $t->dependencies->pluck('task_code')->all()])->all();
        if ($cycle = TemplateImporter::findCycle($graph)) {
            throw ValidationException::withMessages(['depends_on' => 'This creates a dependency loop: '.implode(' → ', $cycle)]);
        }
    }

    private static function saveFacility(?int $tid, Request $r, ?int $id): void
    {
        $tier = 'required|in:Y,Opt,-';
        $data = $r->validate([
            'facility_code' => ['required', 'string', 'max:12', Rule::unique('facility_masters')->where('tenant_id', $tid)->ignore($id)],
            'category' => 'required|string|max:60',
            'name' => 'required|string|max:150',
            'specification' => 'nullable|string|max:255',
            'unit' => 'required|string|max:30',
            'tier_basic' => $tier, 'tier_standard' => $tier, 'tier_premium' => $tier,
            'cost_village' => 'required|numeric|gt:0', 'cost_town' => 'required|numeric|gt:0', 'cost_city' => 'required|numeric|gt:0',
        ]);
        $data['is_statutory'] = $r->boolean('is_statutory');
        $data['is_active'] = $r->boolean('is_active', true);
        if ($id) {
            FacilityMaster::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($id)->update($data);
        } else {
            $data['sino'] = (int) FacilityMaster::withoutGlobalScopes()->where('tenant_id', $tid)->max('sino') + 1;
            FacilityMaster::create($data + ['tenant_id' => $tid]);
        }
    }

    private static function saveDocument(?int $tid, Request $r, ?int $id): void
    {
        $data = $r->validate([
            'checklist' => 'required|string|max:60',
            'doc_code' => ['required', 'string', 'max:12', Rule::unique('document_checklists')->where('tenant_id', $tid)->ignore($id)],
            'applies_to' => 'required|string|max:30',
            'name' => 'required|string|max:255',
            'issued_by' => 'nullable|string|max:150',
            'submission_format' => 'nullable|string|max:100',
            'mandatory' => 'required|in:Yes,No,Conditional',
            'remarks' => 'nullable|string|max:500',
            'stage_no' => 'nullable|integer|min:1|max:99',
            'village_task_code' => 'nullable|string|max:12',
            'town_task_code' => 'nullable|string|max:12',
            'city_task_code' => 'nullable|string|max:12',
        ]);
        foreach (['village', 'town', 'city'] as $a) {
            $code = $data[$a.'_task_code'] ?? null;
            if ($code && strtoupper($code) !== 'N/A' && ! SubtaskMaster::withoutGlobalScopes()->where('tenant_id', $tid)->where('task_code', $code)->exists()) {
                throw ValidationException::withMessages([$a.'_task_code' => "Task ID $code does not exist."]);
            }
            if ($code && strtoupper($code) === 'N/A') {
                $data[$a.'_task_code'] = null;
            }
        }
        $data['is_active'] = $r->boolean('is_active', true);
        DB::transaction(function () use ($tid, $id, $data) {
            if ($id) {
                $doc = tap(DocumentChecklist::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($id))->update($data);
            } else {
                $data['sino'] = (int) DocumentChecklist::withoutGlobalScopes()->where('tenant_id', $tid)->max('sino') + 1;
                $doc = DocumentChecklist::create($data + ['tenant_id' => $tid]);
            }
            // keep Task → Document links in step with the per-area task IDs
            foreach (['village', 'town', 'city'] as $a) {
                if ($code = $doc->{$a.'_task_code'}) {
                    SubtaskMaster::withoutGlobalScopes()->where('tenant_id', $tid)->where('task_code', $code)->first()?->documents()->syncWithoutDetaching([$doc->id]);
                }
            }
        });
    }

    private static function saveSro(Request $r, ?int $id): void
    {
        $data = $r->validate(['state' => 'required|string|max:60', 'district' => 'required|string|max:100', 'taluk' => 'nullable|string|max:100', 'name' => 'required|string|max:150', 'address' => 'nullable|string|max:255']);
        $id ? Sro::findOrFail($id)->update($data) : Sro::create($data);
    }
}
