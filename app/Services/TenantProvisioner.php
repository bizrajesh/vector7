<?php

namespace App\Services;

use App\Models\DocumentChecklist;
use App\Models\ExpenseCategory;
use App\Models\FacilityMaster;
use App\Models\InstalmentPlan;
use App\Models\NotificationGroup;
use App\Models\Plan;
use App\Models\RefundPenaltyRule;
use App\Models\Role;
use App\Models\StageMaster;
use App\Models\SubtaskMaster;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioner
{
    public const DEFAULT_DISCLAIMERS = [
        'disclaimer_booking' => "Booking is valid for the booking validity period in working days. If the first instalment is not paid within this period, the booking lapses and the plot is released without further notice. Prices shown include the offer price only while the offer is valid on the booking date.",
        'disclaimer_sale' => "All instalments must be paid within the sale completion window. Payments are accepted only from the customer who booked the plot. Registration charges, stamp duty and other statutory fees are payable by the buyer.",
        'disclaimer_registration' => "The buyer must provide valid identity proof, PAN and two witnesses on the registration date. The sale deed will be registered at the Sub-Registrar Office having jurisdiction over the property.",
        'disclaimer_refund' => "If the sale is not completed within the sale completion window, the refund is the amount paid less the penalty in the refund penalty table. Refunds are processed after management approval.",
    ];

    /** Create a tenant with its admin user, settings, roles, groups, ID seeds and trial subscription. */
    public static function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'code' => Tenant::generateCode(),
                'type' => $data['type'] ?? 'organisation',
                'name' => $data['name'],
                'slug' => self::slug($data['name']),
                'email' => strtolower($data['email']),
                'mobile' => $data['mobile'] ?? null,
                'contact' => $data['mobile'] ?? null,
                'city' => $data['city'] ?? null,
                'district' => $data['district'] ?? null,
                'state' => $data['state'] ?? 'Tamil Nadu',
                'terms_accepted_at' => now(),
            ]);

            return app(Tenancy::class)->run($tenant, function () use ($tenant, $data) {
                self::defaults($tenant);
                $plan = Plan::where('is_trial_default', true)->first() ?? Plan::orderBy('sort')->first();
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => 'trial',
                    'starts_on' => today(),
                    'ends_on' => today()->addDays(max(1, $plan->trial_days ?: 14)),
                ]);
                $admin = User::create([
                    'tenant_id' => $tenant->id,
                    'role_id' => Role::systemRole('tenant_admin', $tenant->id)->id,
                    'user_code' => IdGenerator::next($tenant->id, 'user'),
                    'name' => $data['admin_name'] ?? $data['name'],
                    'email' => strtolower($data['email']),
                    'mobile' => $data['mobile'] ?? null,
                    'password' => $data['password'],
                ]);
                NotificationGroup::where('tenant_id', $tenant->id)->where('name', 'Management')->first()?->users()->attach($admin->id);

                return [$tenant, $admin];
            });
        });
    }

    public static function defaults(Tenant $tenant): void
    {
        Role::seedTenantRoles($tenant->id);
        IdGenerator::seedDefaults($tenant->id);
        TenantSetting::firstOrCreate(['tenant_id' => $tenant->id], self::DEFAULT_DISCLAIMERS);
        if (! InstalmentPlan::where('tenant_id', $tenant->id)->exists()) {
            foreach ([[1, '1st Instalment', 30, 0], [2, '2nd Instalment', 60, 7], [3, '3rd Instalment', 10, 13]] as [$seq, $name, $pct, $days]) {
                InstalmentPlan::create(['tenant_id' => $tenant->id, 'seq' => $seq, 'name' => $name, 'percent' => $pct, 'due_working_days' => $days]);
            }
        }
        if (! RefundPenaltyRule::where('tenant_id', $tenant->id)->exists()) {
            foreach ([[1, 15, 'percent', 5], [16, 30, 'percent', 10], [31, null, 'percent', 20]] as [$from, $to, $type, $val]) {
                RefundPenaltyRule::create(['tenant_id' => $tenant->id, 'from_days' => $from, 'to_days' => $to, 'penalty_type' => $type, 'value' => $val]);
            }
        }
        foreach ([['Management', 'Project status changes, budget alerts, paused projects'], ['Sales Team', 'Bookings created or expiring, instalments due or overdue'], ['Accounts', 'Receipts, expenses, budget alerts']] as [$name, $desc]) {
            NotificationGroup::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name], ['type' => 'Email', 'description' => $desc]);
        }
        foreach (['Approval fees', 'Survey & legal', 'Civil works', 'Electrical', 'Marketing', 'Broker commission', 'Refunds', 'Office & admin', 'Other'] as $cat) {
            ExpenseCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $cat]);
        }
    }

    /** Copy the App-level masters (tenant_id NULL) into the tenant's own editable copies. */
    public static function copyAppMasters(Tenant $tenant, bool $replace = true): void
    {
        DB::transaction(function () use ($tenant, $replace) {
            if ($replace) {
                StageMaster::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
                FacilityMaster::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
                DocumentChecklist::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            }
            $docMap = [];
            foreach (DocumentChecklist::withoutGlobalScopes()->whereNull('tenant_id')->get() as $d) {
                $copy = $d->replicate(['tenant_id']);
                $copy->tenant_id = $tenant->id;
                $copy->save();
                $docMap[$d->id] = $copy->id;
            }
            foreach (FacilityMaster::withoutGlobalScopes()->whereNull('tenant_id')->get() as $f) {
                $copy = $f->replicate(['tenant_id']);
                $copy->tenant_id = $tenant->id;
                $copy->save();
            }
            $taskMap = [];
            $stages = StageMaster::withoutGlobalScopes()->whereNull('tenant_id')->with(['subtasks' => fn ($q) => $q->withoutGlobalScopes()->with(['documents' => fn ($d) => $d->withoutGlobalScopes(), 'dependencies' => fn ($d) => $d->withoutGlobalScopes()])])->get();
            foreach ($stages as $s) {
                $sc = $s->replicate(['tenant_id']);
                $sc->tenant_id = $tenant->id;
                $sc->save();
                foreach ($s->subtasks as $t) {
                    $tc = $t->replicate(['tenant_id', 'stage_master_id']);
                    $tc->tenant_id = $tenant->id;
                    $tc->stage_master_id = $sc->id;
                    $tc->save();
                    $taskMap[$t->id] = $tc->id;
                    $docs = $t->documents->pluck('id')->map(fn ($id) => $docMap[$id] ?? null)->filter()->all();
                    if ($docs) {
                        $tc->documents()->attach($docs);
                    }
                }
            }
            foreach ($stages as $s) {
                foreach ($s->subtasks as $t) {
                    $deps = $t->dependencies->pluck('id')->map(fn ($id) => $taskMap[$id] ?? null)->filter()->all();
                    if ($deps) {
                        SubtaskMaster::withoutGlobalScopes()->find($taskMap[$t->id])->dependencies()->attach($deps);
                    }
                }
            }
        });
    }

    private static function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 2;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
