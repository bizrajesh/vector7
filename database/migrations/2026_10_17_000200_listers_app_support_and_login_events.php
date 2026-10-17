<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Property listers, App Support and security monitoring (no data is removed):
 *  - plans.audience: promoter | lister (App team designs plans for each)
 *  - login_events: every sign-in attempt (success / failed / locked / blocked) with IP, browser, portal and session
 *  - App Support role and the "Security monitoring" permission rows
 *  - three starter plans for property listers
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->string('audience', 20)->default('promoter')->after('slug')->index();
        });

        Schema::create('login_events', function (Blueprint $t) {
            $t->id();
            $t->string('portal', 20);                 // buyer | promoter | lister | app
            $t->string('account_type', 20)->nullable(); // user | customer
            $t->unsignedBigInteger('account_id')->nullable();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('email', 255)->nullable();
            $t->string('outcome', 20);                // success | failed | locked | blocked
            $t->string('reason', 80)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->string('session_id', 100)->nullable()->index();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['ip', 'created_at']);
            $t->index(['outcome', 'created_at']);
            $t->index(['email', 'created_at']);
        });

        $now = now();
        foreach (['app_security'] as $module) {
            foreach (config('permissions.actions') as $action) {
                DB::table('permissions')->insertOrIgnore(['key' => "$module.$action", 'module' => $module, 'action' => $action, 'scope' => 'app']);
            }
        }
        if (! DB::table('roles')->whereNull('tenant_id')->where('base_role', 'app_support')->exists()) {
            DB::table('roles')->insert(['tenant_id' => null, 'name' => 'App Support', 'slug' => 'app-support', 'base_role' => 'app_support', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        if (DB::table('plans')->exists() && ! DB::table('plans')->where('audience', 'lister')->exists()) {
            foreach (\App\Services\ListerPlans::defaults() as $i => [$name, $slug, $desc, $price, $default, $limits]) {
                $id = DB::table('plans')->insertGetId(['name' => $name, 'slug' => $slug, 'audience' => 'lister', 'description' => $desc, 'price' => $price,
                    'billing_cycle' => 'monthly', 'trial_days' => 14, 'is_active' => true, 'is_trial_default' => $default, 'modules' => json_encode(['leads', 'ai']),
                    'sort' => 10 + $i, 'created_at' => $now, 'updated_at' => $now]);
                foreach ($limits as $key => $value) {
                    DB::table('plan_limits')->insert(['plan_id' => $id, 'key' => $key, 'value' => $value]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('plans')->where('audience', 'lister')->pluck('id');
        if (! DB::table('subscriptions')->whereIn('plan_id', $ids)->exists()) {
            DB::table('plan_limits')->whereIn('plan_id', $ids)->delete();
            DB::table('plans')->whereIn('id', $ids)->delete();
        }
        Schema::dropIfExists('login_events');
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn('audience'));
    }
};
