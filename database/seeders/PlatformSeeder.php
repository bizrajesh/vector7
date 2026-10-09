<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\Notify;
use Illuminate\Database\Seeder;

/** Permissions catalog, App roles, App Admin (from .env), plans, email templates, services. */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['app' => config('permissions.app_modules'), 'tenant' => config('permissions.tenant_modules')] as $scope => $modules) {
            foreach ($modules as $module => $label) {
                foreach (config('permissions.actions') as $action) {
                    Permission::firstOrCreate(['key' => "$module.$action"], ['module' => $module, 'action' => $action, 'scope' => $scope]);
                }
            }
        }

        foreach (Role::APP_ROLES as $base) {
            Role::firstOrCreate(['tenant_id' => null, 'slug' => str_replace('_', '-', $base)], ['name' => config('permissions.role_labels.'.$base), 'base_role' => $base, 'is_system' => true]);
        }

        User::firstOrCreate(['email' => strtolower(env('APP_ADMIN_EMAIL', 'admin@vector7.in'))], [
            'tenant_id' => null,
            'role_id' => Role::whereNull('tenant_id')->where('base_role', 'app_admin')->value('id'),
            'name' => env('APP_ADMIN_NAME', 'Vector7 Admin'),
            'password' => env('APP_ADMIN_PASSWORD', 'ChangeMe@2026'),
        ]);

        $allModules = array_keys(config('permissions.plan_modules'));
        $plans = [
            ['Starter', 'starter', 'For individual promoters launching their first layout.', 1999, 14, true, ['masters', 'estimates', 'tracking', 'launch', 'bookings', 'accounts'],
                ['storage_mb' => 1024, 'projects' => 2, 'ai_credits' => 50, 'users_tenant_admin' => 1, 'users_tenant_manager' => 1, 'users_tenant_sales' => 2, 'users_tenant_account' => 1, 'users_support' => 1]],
            ['Professional', 'professional', 'For growing promoters with several active layouts.', 4999, 14, false, $allModules,
                ['storage_mb' => 10240, 'projects' => 10, 'ai_credits' => 500, 'users_tenant_admin' => 2, 'users_tenant_manager' => 3, 'users_tenant_sales' => 10, 'users_tenant_account' => 3, 'users_support' => 3]],
            ['Enterprise', 'enterprise', 'Unlimited projects and users for large organisations.', 14999, 14, false, $allModules,
                ['storage_mb' => 102400, 'projects' => -1, 'ai_credits' => 5000, 'users_tenant_admin' => -1, 'users_tenant_manager' => -1, 'users_tenant_sales' => -1, 'users_tenant_account' => -1, 'users_support' => -1]],
        ];
        foreach ($plans as $i => [$name, $slug, $desc, $price, $trial, $default, $modules, $limits]) {
            $plan = Plan::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $desc, 'price' => $price, 'billing_cycle' => 'monthly', 'trial_days' => $trial,
                'is_trial_default' => $default, 'modules' => $modules, 'sort' => $i,
            ]);
            foreach ($limits as $key => $value) {
                $plan->limits()->firstOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        Notify::seedTemplates();

        $services = [
            ['Legal title opinion', 'Advocate-verified title scrutiny of parent documents, EC and patta before you buy or develop.', null, true, 'both'],
            ['Land survey & FMB sketch', 'Licensed surveyor visit, boundary fixing and FMB-based survey sketch.', 15000, false, 'both'],
            ['Registration assistance', 'Document writer support, SRO appointment and checklist for sale-deed registration.', 7500, false, 'customer'],
            ['Home loan assistance', 'Help with plot-loan eligibility and paperwork with partner banks.', null, true, 'customer'],
            ['Layout approval consulting', 'End-to-end DTCP / panchayat approval support for promoters.', null, true, 'tenant'],
            ['Digital marketing', 'Social media campaigns and listing promotion for launched layouts.', 9999, false, 'tenant'],
        ];
        foreach ($services as $i => [$name, $desc, $price, $onReq, $to]) {
            Service::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], [
                'name' => $name, 'summary' => $desc, 'description' => $desc, 'price' => $price, 'price_on_request' => $onReq, 'available_to' => $to, 'sort' => $i,
            ]);
        }
    }
}
