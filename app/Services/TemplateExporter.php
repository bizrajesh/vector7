<?php

namespace App\Services;

use App\Models\DocumentChecklist;
use App\Models\FacilityMaster;
use App\Models\InstalmentPlan;
use App\Models\NotificationGroup;
use App\Models\StageMaster;
use App\Models\Tenant;
use App\Models\User;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** Writes the tenant's (or the App's) current setup back into the pre-configuration template format. */
class TemplateExporter
{
    public static function write(?Tenant $tenant, string $path): void
    {
        $tid = $tenant?->id;
        $w = new Writer;
        $w->openToFile($path);
        $title = (new Style)->withFontBold(true)->withFontSize(14);
        $head = (new Style)->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('0B1B33');
        $first = true;
        $sheet = function (string $name, string $subtitle, array $headers, array $rows) use ($w, $title, $head, &$first) {
            if ($first) {
                $w->getCurrentSheet()->setName($name);
                $first = false;
            } else {
                $w->addNewSheetAndMakeItCurrent()->setName($name);
            }
            $w->addRow(Row::fromValuesWithStyle([$name], $title));
            $w->addRow(Row::fromValues([$subtitle]));
            $w->addRow(Row::fromValues(['']));
            $w->addRow(Row::fromValuesWithStyle($headers, $head));
            foreach ($rows as $r) {
                $w->addRow(Row::fromValues($r));
            }
        };

        if ($tenant) {
            $s = $tenant->setting();
            $profile = [
                ['Identity', 'Name or Company Name', $tenant->name, 'Yes'], ['Identity', 'Profile Picture', $tenant->logo_path ? 'logo uploaded' : '', 'No'],
                ['Address', 'Address Line1', $tenant->address_line1, 'Yes'], ['Address', 'Address Line2', $tenant->address_line2, 'No'],
                ['Address', 'Village', $tenant->village, 'No'], ['Address', 'City Or Town', $tenant->city, 'Yes'], ['Address', 'District', $tenant->district, 'Yes'],
                ['Address', 'State', $tenant->state, 'Yes'], ['Address', 'Pin Code', $tenant->pin, 'Yes'],
                ['Contact', 'Contact Number', $tenant->contact, 'Yes'], ['Contact', 'Alternate Contact', $tenant->alt_contact, 'No'],
                ['Social', 'Insta', $tenant->instagram, 'No'], ['Social', 'Face Book', $tenant->facebook, 'No'], ['Social', 'Website', $tenant->website, 'No'],
                ['Social', 'Youtube', $tenant->youtube, 'No'], ['Social', 'LinkedIn', $tenant->linkedin, 'No'],
            ];
            $sheet('Tenant Profile', 'Fill the Value column. Required fields must not be blank.', ['Sino', 'Section', 'Field', 'Value', 'Required'],
                array_map(fn ($r, $i) => [$i + 1, $r[0], $r[1], (string) $r[2], $r[3]], $profile, array_keys($profile)));

            $rows = [
                ['App Settings', 'Currency', 'Code', $s->currency, null], ['App Settings', 'Sellable SQFT %', 'Percentage', (float) $s->sellable_pct, null],
                ['App Settings', 'Broker Commission', 'Percentage', (float) $s->broker_commission_pct, null], ['App Settings', 'Expense vs Budget Alert', 'Percentage', (float) $s->budget_alert_pct, null],
                ['App Settings', 'Booking Validity', 'Days', $s->booking_validity_days, null], ['App Settings', 'Sale Completion Window', 'Days', $s->sale_window_days, null],
                ['App Settings', 'MRP Multiplier', 'Times', (float) $s->mrp_multiplier, null],
            ];
            foreach (InstalmentPlan::withoutGlobalScopes()->where('tenant_id', $tid)->orderBy('seq')->get() as $i) {
                $rows[] = ['Instalment', $i->name, 'Percentage', (float) $i->percent, $i->due_working_days];
            }
            $sheet('Additional Settings', 'Importer reads columns A–F.', ['Sino', 'Type', 'Item', 'Unit', 'Unit Value', 'Due Working Days'],
                array_map(fn ($r, $i) => array_merge([$i + 1], $r), $rows, array_keys($rows)));

            $groups = NotificationGroup::withoutGlobalScopes()->where('tenant_id', $tid)->get();
            $sheet('Notification Groups', 'Email only in this release.', ['Sino', 'Group Name', 'Type', 'Description', 'Active'],
                $groups->values()->map(fn ($g, $i) => [$i + 1, $g->name, $g->type, (string) $g->description, $g->is_active ? 'Yes' : 'No'])->all());

            $roleBack = array_flip(['Admin' => 'tenant_admin', 'Manager' => 'tenant_manager', 'Sales' => 'tenant_sales', 'Accountant' => 'tenant_account', 'Support' => 'support']);
            $users = User::withoutGlobalScopes()->where('tenant_id', $tid)->with(['role' => fn ($q) => $q->withoutGlobalScopes(), 'groups' => fn ($q) => $q->withoutGlobalScopes()])->get();
            $sheet('Users', 'One row per login user.', ['Sino', 'Full Name', 'Email', 'Mobile', 'Role', 'Notification Groups', 'Active'],
                $users->values()->map(fn ($u, $i) => [$i + 1, $u->name, $u->email, (string) $u->mobile, $roleBack[$u->role->base_role] ?? 'Sales', $u->groups->pluck('name')->implode(', '), $u->is_active ? 'Yes' : 'No'])->all());
        }

        $stages = StageMaster::withoutGlobalScopes()->where('tenant_id', $tid)->orderByRaw("FIELD(area_type,'Village','Town','City')")->orderBy('stage_no')
            ->with(['subtasks' => fn ($q) => $q->withoutGlobalScopes()->with(['dependencies' => fn ($d) => $d->withoutGlobalScopes(), 'documents' => fn ($d) => $d->withoutGlobalScopes()])])->get();
        $rows = [];
        $n = 1;
        foreach ($stages as $st) {
            foreach ($st->subtasks as $t) {
                $rows[] = [$n++, $st->area_type, $st->stage_no, $st->name, $t->task_code, $t->short_name, $t->name, $t->dependencies->pluck('task_code')->implode(', '),
                    (string) $t->responsible, (string) $t->output, $t->default_duration_days, $t->documents->pluck('doc_code')->implode(', '), $t->documents->count(), $t->is_active ? 'Yes' : 'No'];
            }
        }
        $sheet('Approval Stages', 'One row per subtask. Depends On lists Task IDs (comma-separated).',
            ['Sino', 'Area Type', 'Stage No', 'Stage', 'Task ID', 'Short Name', 'Subtask', 'Depends On', 'Responsible / Authority', 'Output / Document', 'Default Duration (Days)', 'Required Doc IDs', 'Doc Count', 'Active'], $rows);

        $sheet('Facility Master', 'Tier flags: Y = included · Opt = optional · - = not included. Unit costs in ₹, excl. GST.',
            ['Sino', 'Facility ID', 'Category', 'Facility', 'Specification', 'Unit', 'Basic', 'Standard', 'Premium', 'Statutory', 'Village Unit Cost (₹)', 'Town Unit Cost (₹)', 'City Unit Cost (₹)', 'Active'],
            FacilityMaster::withoutGlobalScopes()->where('tenant_id', $tid)->orderBy('sino')->get()->map(fn ($f) => [
                $f->sino, $f->facility_code, $f->category, $f->name, (string) $f->specification, $f->unit, $f->tier_basic, $f->tier_standard, $f->tier_premium,
                $f->is_statutory ? 'Yes' : 'No', (float) $f->cost_village, (float) $f->cost_town, (float) $f->cost_city, $f->is_active ? 'Yes' : 'No',
            ])->all());

        $sheet('Document Checklist', 'One row per document, mapped to a Stage and Task ID per area (N/A = not applicable).',
            ['Sino', 'Checklist', 'Doc ID', 'Applies To', 'Document / Item', 'Issued By / Source', 'Submission Format', 'Mandatory', 'Condition / Remarks', 'Stage No', 'Stage', 'Village Task ID', 'Town Task ID', 'City Task ID', 'Active'],
            DocumentChecklist::withoutGlobalScopes()->where('tenant_id', $tid)->orderBy('sino')->get()->map(fn ($d) => [
                $d->sino, $d->checklist, $d->doc_code, $d->applies_to, $d->name, (string) $d->issued_by, (string) $d->submission_format, $d->mandatory, (string) $d->remarks,
                $d->stage_no, (string) $d->stage_name, $d->village_task_code ?? 'N/A', $d->town_task_code ?? 'N/A', $d->city_task_code ?? 'N/A', $d->is_active ? 'Yes' : 'No',
            ])->all());

        $w->close();
    }
}
