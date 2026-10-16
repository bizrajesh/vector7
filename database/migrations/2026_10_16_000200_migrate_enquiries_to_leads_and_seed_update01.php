<?php

use App\Models\LeadStage;
use App\Services\Notify;
use Database\Seeders\FeatureSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data for Feature Update 01 on an existing database (no data is removed):
 * permissions catalogue, plan limits (active listings, images per listing) and the Leads module,
 * Listing / Lead ID seeds, default lead stages, default listing contact, App features master,
 * new email templates, and every tenant enquiry copied into Leads (source "Enquiry form").
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Permissions catalogue
        $actions = config('permissions.actions');
        foreach (['app' => config('permissions.app_modules'), 'tenant' => config('permissions.tenant_modules')] as $scope => $modules) {
            foreach (array_keys($modules) as $module) {
                foreach ($actions as $action) {
                    DB::table('permissions')->insertOrIgnore(['key' => "$module.$action", 'module' => $module, 'action' => $action, 'scope' => $scope]);
                }
            }
        }

        // Plan limits + Leads module
        $defaults = ['starter' => ['listings' => 10, 'images_per_listing' => 12], 'professional' => ['listings' => 100, 'images_per_listing' => 20], 'enterprise' => ['listings' => -1, 'images_per_listing' => 20]];
        foreach (DB::table('plans')->get() as $plan) {
            foreach ($defaults[$plan->slug] ?? ['listings' => 25, 'images_per_listing' => 20] as $key => $value) {
                DB::table('plan_limits')->insertOrIgnore(['plan_id' => $plan->id, 'key' => $key, 'value' => $value]);
            }
            $modules = $plan->modules ? json_decode($plan->modules, true) : null;
            if (is_array($modules) && ! in_array('leads', $modules, true)) {
                $modules[] = 'leads';
                DB::table('plans')->where('id', $plan->id)->update(['modules' => json_encode(array_values($modules))]);
            }
        }

        // Per tenant: ID seeds, lead stages, default listing contact
        foreach (DB::table('tenants')->get() as $tenant) {
            DB::table('id_sequences')->insertOrIgnore([
                ['tenant_id' => $tenant->id, 'type' => 'listing', 'prefix' => 'L', 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->id, 'type' => 'lead', 'prefix' => 'LD', 'created_at' => $now, 'updated_at' => $now],
            ]);
            foreach (LeadStage::DEFAULTS as $i => [$key, $name, $color, $won, $lost]) {
                DB::table('lead_stages')->insertOrIgnore(['tenant_id' => $tenant->id, 'key' => $key, 'name' => $name, 'sort' => $i, 'color' => $color, 'is_won' => $won, 'is_lost' => $lost, 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('tenant_settings')->where('tenant_id', $tenant->id)->whereNull('contact_mobile')->update([
                'contact_name' => $tenant->name, 'contact_mobile' => $tenant->contact ?: $tenant->mobile,
                'contact_whatsapp' => $tenant->contact ?: $tenant->mobile, 'contact_email' => $tenant->support_email ?: $tenant->email,
            ]);
        }

        // Tenant enquiries → leads (kept in enquiries too; leads.enquiry_id links them)
        $stageMap = ['new' => 'new', 'contacted' => 'contacted', 'converted' => 'won', 'closed' => 'lost'];
        foreach (DB::table('enquiries')->whereNotNull('tenant_id')->orderBy('id')->get() as $e) {
            if (DB::table('leads')->where('enquiry_id', $e->id)->exists()) {
                continue;
            }
            $stageKey = $stageMap[$e->status] ?? 'new';
            $stageId = DB::table('lead_stages')->where('tenant_id', $e->tenant_id)->where('key', $stageKey)->value('id');
            $created = \Carbon\Carbon::parse($e->created_at);
            $leadId = DB::table('leads')->insertGetId([
                'tenant_id' => $e->tenant_id, 'lead_code' => 'LD'.$created->format('dmYis').'-E'.$e->id, 'name' => $e->name, 'mobile' => $e->mobile,
                'email' => $e->email, 'customer_id' => $e->customer_id, 'source' => 'enquiry', 'notes' => $e->notes, 'assigned_to' => $e->assigned_to,
                'lead_stage_id' => $stageId, 'priority' => 'warm', 'next_follow_up_at' => $e->follow_up_on ? \Carbon\Carbon::parse($e->follow_up_on)->setTime(10, 0) : null,
                'consent' => true, 'lost_reason' => $stageKey === 'lost' ? 'other' : null, 'won_at' => $stageKey === 'won' ? $e->updated_at : null,
                'lost_at' => $stageKey === 'lost' ? $e->updated_at : null, 'last_activity_at' => $e->updated_at, 'enquiry_id' => $e->id,
                'created_at' => $e->created_at, 'updated_at' => $e->updated_at,
            ]);
            if ($e->project_id || $e->plot_id) {
                DB::table('lead_interests')->insert(['tenant_id' => $e->tenant_id, 'lead_id' => $leadId, 'project_id' => $e->project_id, 'plot_id' => $e->plot_id, 'created_at' => $e->created_at, 'updated_at' => $e->created_at]);
            }
            DB::table('lead_activities')->insert(['tenant_id' => $e->tenant_id, 'lead_id' => $leadId, 'type' => 'enquiry', 'body' => $e->message, 'created_at' => $e->created_at]);
        }

        (new FeatureSeeder)->run();
        Notify::seedTemplates();
    }

    public function down(): void
    {
        DB::table('leads')->whereNotNull('enquiry_id')->delete();
        DB::table('plan_limits')->whereIn('key', ['listings', 'images_per_listing'])->delete();
        DB::table('id_sequences')->whereIn('type', ['listing', 'lead'])->delete();
    }
};
