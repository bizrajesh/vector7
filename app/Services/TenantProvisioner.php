<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Facility;
use App\Models\LedgerCategory;
use App\Models\Plan;
use App\Models\StageGroup;
use App\Models\StageTemplate;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Self-registration (section 3A.2): tenant + Admin user + subscription + default masters,
 * all in one transaction.
 */
class TenantProvisioner
{
    public function __construct(private readonly TenantContext $context) {}

    public function register(array $data, Plan $plan, string $cycle): User
    {
        return DB::transaction(function () use ($data, $plan, $cycle) {
            $tenant = new Tenant([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'gstin' => $data['gstin'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'],
            ]);
            $tenant->plan_id = $plan->id;
            $tenant->status = SubscriptionStatus::Trial;
            $tenant->save();

            $subscription = new Subscription([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'cycle' => $cycle,
                'status' => SubscriptionStatus::Trial,
                'trial_ends_at' => now()->addDays(max(1, (int) $plan->trial_days)),
            ]);
            $subscription->save();

            $admin = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);
            $admin->tenant_id = $tenant->id;
            $admin->role = Role::Admin;
            $admin->status = 'active';
            $admin->save();

            $this->context->run($tenant, fn () => $this->seed());

            return $admin;
        });
    }

    /** Default master data so a new tenant can start immediately. */
    public function seed(): void
    {
        foreach (config('vector7.seed.facilities') as [$name, $unit, $deduct]) {
            Facility::create(['name' => $name, 'unit' => $unit, 'unit_cost' => 0, 'deduct_from_sellable' => $deduct, 'is_active' => true]);
        }

        $template = ChecklistTemplate::create(['name' => 'Registration checklist', 'purpose' => 'registration']);
        foreach (config('vector7.seed.registration_checklist') as $i => [$label, $upload, $date]) {
            $item = new ChecklistTemplateItem(['label' => $label, 'is_required' => true, 'needs_upload' => $upload, 'needs_date' => $date, 'sort_order' => $i + 1]);
            $item->checklist_template_id = $template->id;
            $item->save();
        }

        foreach (config('vector7.seed.ledger_categories') as [$name, $direction]) {
            $category = new LedgerCategory(['name' => $name, 'direction' => $direction]);
            $category->is_system = true;
            $category->save();
        }

        $group = StageGroup::create(['name' => 'Standard DTCP layout', 'description' => 'Sample group — edit to match your approvals', 'is_active' => true]);
        foreach ([['Feasibility', 1000, 4], ['Document Validation', 5000, 4], ['Agri NOC', 10000, 4]] as $i => [$name, $cost, $days]) {
            $stage = new StageTemplate([
                'stage_no' => sprintf('SG%03d', $i + 1), 'seq_no' => $i + 1, 'name' => $name, 'cost' => $cost,
                'duration_days' => $days, 'stage_type' => 'independent', 'is_mandatory' => true,
            ]);
            $stage->stage_group_id = $group->id;
            $stage->save();
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug(Str::limit($name, 40, '')) ?: 'tenant';
        $slug = $base;
        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
