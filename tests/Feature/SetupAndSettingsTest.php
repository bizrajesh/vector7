<?php

namespace Tests\Feature;

use App\Models\FacilityMaster;
use App\Models\Holiday;
use App\Models\InstalmentPlan;
use App\Models\Plan;
use App\Models\StageMaster;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\IdGenerator;
use App\Services\PlanLimiter;
use App\Services\SubscriptionService;
use App\Services\TemplateImporter;
use App\Services\WorkingDays;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class SetupAndSettingsTest extends TestCase
{
    public function test_create_workspace_without_otp_generates_tenant_id_and_opens_wizard(): void
    {
        $r = $this->post('/workspace/create', [
            'type' => 'organisation', 'name' => 'Sri Murugan Promoters', 'email' => 'owner@murugan.in', 'mobile' => '9876543210',
            'password' => 'Owner@2026', 'password_confirmation' => 'Owner@2026', 'terms' => '1',
        ]);
        $r->assertRedirect(route('ws.setup'));
        $t = Tenant::where('name', 'Sri Murugan Promoters')->first();
        $this->assertMatchesRegularExpression('/^T\d{8}\d{4}\d{4}$/', $t->code);
        $this->assertSame('T'.now()->format('dmY'), substr($t->code, 0, 9));
        $this->assertSame('trial', $t->subscription->status);
        $this->assertAuthenticated('web');
        $this->assertTrue(User::where('email', 'owner@murugan.in')->first()->isTenantAdmin());
        $this->get(route('ws.setup'))->assertOk()->assertSee($t->code);
    }

    public function test_app_template_seeded_from_attached_file(): void
    {
        $this->assertSame(39, StageMaster::withoutGlobalScopes()->whereNull('tenant_id')->count());
        $this->assertSame(40, FacilityMaster::withoutGlobalScopes()->whereNull('tenant_id')->count());
    }

    public function test_use_app_defaults_copies_masters_to_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $this->actingAs($admin)->post(route('ws.setup.defaults'))->assertRedirect();
        $this->assertSame(39, StageMaster::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $task = \App\Models\SubtaskMaster::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('task_code', 'V1.3')->first();
        $this->assertEqualsCanonicalizing(['V1.1', 'V1.2'], $task->dependencies()->withoutGlobalScopes()->pluck('task_code')->all());
    }

    public function test_template_import_validates_and_imports_nothing_on_error(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        // The attached template has blank required profile fields → errors, nothing imported
        $file = new UploadedFile(database_path('seeders/data/Vector7_Tenant_PreConfig_Template.xlsx'), 'tpl.xlsx', null, null, true);
        $this->actingAs($admin)->post(route('ws.setup.template'), ['file' => $file])->assertSessionHas('import_errors');
        $this->assertSame(0, StageMaster::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $errors = collect(session('import_errors'))->pluck('message')->implode(' | ');
        $this->assertStringContainsString('Pin Code is required', $errors);
    }

    public function test_template_round_trip_export_then_import(): void
    {
        [$tenant, $admin] = $this->makeTenant('Round Trip Co', true);
        $tenant->update(['address_line1' => '12 Main St', 'city' => 'Thanjavur', 'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'pin' => '613001', 'contact' => '9876543210']);
        $path = sys_get_temp_dir().'/rt.xlsx';
        \App\Services\TemplateExporter::write($tenant->fresh(), $path);
        $imp = TemplateImporter::fromFile($path);
        $this->assertTrue($imp->validate(true, $tenant->id), json_encode(array_slice($imp->errors, 0, 5)));
        $summary = $imp->import($tenant);
        $this->assertSame(148, $summary['tasks']);
        $this->assertSame(110, $summary['documents']);
    }

    public function test_template_validation_rules(): void
    {
        $imp = new TemplateImporter;
        $imp->data = [
            'Tenant Profile' => [['Field' => 'Pin Code', 'Value' => '6130', 'Required' => 'Yes', '_row' => 13]],
            'Additional Settings' => [
                ['Sino' => 1, 'Type' => 'App Settings', 'Item' => 'Sale Completion Window', 'Unit' => 'Days', 'Unit Value' => 15, 'Due Working Days' => null, '_row' => 5],
                ['Sino' => 2, 'Type' => 'Instalment', 'Item' => '1st', 'Unit' => 'Percentage', 'Unit Value' => 30, 'Due Working Days' => 0, '_row' => 6],
                ['Sino' => 3, 'Type' => 'Instalment', 'Item' => '2nd', 'Unit' => 'Percentage', 'Unit Value' => 60, 'Due Working Days' => 20, '_row' => 7],
                ['Sino' => 3, 'Type' => 'App Settings', 'Item' => 'Broker Commission', 'Unit' => 'Percentage', 'Unit Value' => 120, 'Due Working Days' => null, '_row' => 8],
            ],
            'Notification Groups' => [], 'Users' => [],
            'Approval Stages' => [
                ['Sino' => 1, 'Area Type' => 'Village', 'Stage No' => 1, 'Stage' => 'S', 'Task ID' => 'V1.1', 'Short Name' => 'a', 'Subtask' => 'a', 'Depends On' => 'V1.2', 'Default Duration (Days)' => 1, 'Required Doc IDs' => 'X-9', 'Active' => 'Yes', '_row' => 5],
                ['Sino' => 2, 'Area Type' => 'Village', 'Stage No' => 1, 'Stage' => 'S', 'Task ID' => 'V1.2', 'Short Name' => 'b', 'Subtask' => 'b', 'Depends On' => 'V1.1', 'Default Duration (Days)' => 1, 'Required Doc IDs' => null, 'Active' => 'Yes', '_row' => 6],
            ],
            'Facility Master' => [], 'Document Checklist' => [],
        ];
        $this->assertFalse($imp->validate(true));
        $all = collect($imp->errors)->pluck('message')->implode(' | ');
        $this->assertStringContainsString('Pin Code must be exactly 6 digits', $all);
        $this->assertStringContainsString('must total 100', $all);
        $this->assertStringContainsString('must be ≤ the Sale Completion Window', $all);
        $this->assertStringContainsString('between 0 and 100', $all);
        $this->assertStringContainsString('Sino 3 is repeated', $all);
        $this->assertStringContainsString('cycle', $all);
        $this->assertStringContainsString('Required Doc ID X-9 does not exist', $all);
        $this->assertStringContainsString('At least one active notification group', $all);
    }

    public function test_instalment_plan_must_total_100_and_fit_window(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $base = ['booking_validity_days' => 7, 'sale_window_days' => 15, 'currency' => 'INR', 'sellable_pct' => 55, 'broker_commission_pct' => 0.5, 'budget_alert_pct' => 80, 'mrp_multiplier' => 3];
        $this->actingAs($admin)->put(route('ws.settings.sales'), $base + ['inst' => [['name' => 'A', 'percent' => 30, 'due_working_days' => 0], ['name' => 'B', 'percent' => 60, 'due_working_days' => 7]]])->assertSessionHas('error');
        $this->actingAs($admin)->put(route('ws.settings.sales'), $base + ['inst' => [['name' => 'A', 'percent' => 40, 'due_working_days' => 0], ['name' => 'B', 'percent' => 60, 'due_working_days' => 16]]])->assertSessionHas('error');
        $this->actingAs($admin)->put(route('ws.settings.sales'), $base + ['inst' => [['name' => 'A', 'percent' => 40, 'due_working_days' => 0], ['name' => 'B', 'percent' => 60, 'due_working_days' => 15]],
            'pen' => [['from_days' => 1, 'to_days' => 10, 'penalty_type' => 'percent', 'value' => 5]]])->assertSessionHas('ok');
        $this->assertSame([40.0, 60.0], InstalmentPlan::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('seq')->pluck('percent')->map(fn ($p) => (float) $p)->all());
    }

    public function test_id_seeds_use_prefix_and_clock_with_clash_suffix(): void
    {
        [$tenant] = $this->makeTenant();
        Carbon::setTestNow('2026-10-09 14:35:47');
        \App\Models\IdSequence::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', 'booking')->update(['prefix' => 'BK']);
        $this->assertSame('BK09102026'.'3547', IdGenerator::next($tenant->id, 'booking'));
        $this->assertSame('PRJ0910202635', IdGenerator::next($tenant->id, 'project'));
        \DB::table('expenses')->insert(['tenant_id' => $tenant->id, 'transaction_no' => 'EXP091020263547', 'description' => 'x', 'amount' => 1, 'spent_on' => '2026-10-09', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('EXP091020263547-2', IdGenerator::next($tenant->id, 'expense'));
        Carbon::setTestNow();
    }

    public function test_working_days_skip_weekends_and_holidays(): void
    {
        [$tenant] = $this->makeTenant();
        Holiday::create(['tenant_id' => $tenant->id, 'date' => '2026-10-12', 'name' => 'Test holiday']); // Monday
        $wd = WorkingDays::for($tenant->id);
        // Fri 9 Oct + 1 working day → Tue 13 Oct (Sat, Sun, Mon holiday skipped)
        $this->assertSame('2026-10-13', $wd->add(Carbon::parse('2026-10-09'), 1)->toDateString());
        $this->assertSame('2026-10-20', $wd->add(Carbon::parse('2026-10-09'), 6)->toDateString());
        $this->assertSame(1, $wd->between(Carbon::parse('2026-10-09'), Carbon::parse('2026-10-13')));
    }

    public function test_plan_usage_left_over_figures(): void
    {
        [$tenant] = $this->makeTenant('Usage Co', false, 'starter');
        $tenant->update(['storage_used_bytes' => 512 * 1048576]);
        $u = PlanLimiter::usage($tenant->fresh());
        $this->assertSame(1024, $u['storage_mb']['limit']);
        $this->assertEquals(512, $u['storage_mb']['used']);
        $this->assertEquals(512, $u['storage_mb']['left']);
        $this->assertEquals(50.0, $u['storage_mb']['pct']);
        $this->assertSame(1, $u['users_tenant_admin']['used']);
        $this->assertSame(0, $u['users_tenant_admin']['left']);
        $this->assertSame('teal', PlanLimiter::level(50));
        $this->assertSame('amber', PlanLimiter::level(85));
        $this->assertSame('red', PlanLimiter::level(100));
        $tenant->update(['storage_used_bytes' => 1024 * 1048576]);
        $this->expectException(\App\Exceptions\PlanLimitException::class);
        PlanLimiter::ensureStorage($tenant->fresh(), 10);
    }

    public function test_manual_mark_paid_activates_subscription_and_webhook_signature_checked(): void
    {
        [$tenant] = $this->makeTenant();
        $plan = Plan::where('slug', 'enterprise')->first();
        $inv = SubscriptionService::createInvoice($tenant, $plan);
        $this->actingAs($this->appAdmin())->post(route('app.invoices.paid', $inv))->assertSessionHas('ok');
        $sub = $tenant->fresh()->subscription;
        $this->assertSame('active', $sub->status);
        $this->assertSame($plan->id, $sub->plan_id);

        AppSettings::set('payment.razorpay_webhook_secret', 'whsec');
        $inv2 = SubscriptionService::createInvoice($tenant, Plan::where('slug', 'starter')->first());
        $body = json_encode(['event' => 'payment_link.paid', 'payload' => ['payment_link' => ['entity' => ['id' => 'plink_x', 'reference_id' => $inv2->number]], 'payment' => ['entity' => ['id' => 'pay_1']]]]);
        $this->call('POST', '/webhooks/payment/razorpay', [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => 'bad', 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400);
        $this->assertSame('due', $inv2->fresh()->status);
        $this->call('POST', '/webhooks/payment/razorpay', [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
        $this->assertSame('paid', $inv2->fresh()->status);
    }

    public function test_expired_subscription_blocks_new_records(): void
    {
        [$tenant] = $this->makeTenant();
        $tenant->subscription->update(['status' => 'expired']);
        $this->expectException(\App\Exceptions\PlanLimitException::class);
        PlanLimiter::ensure($tenant->fresh(), 'projects');
    }

    public function test_screens_render(): void
    {
        [$tenant, $admin] = $this->makeTenant('Render Co', true);
        foreach (['ws.iam.index', 'ws.roles.index', 'ws.groups.index', 'ws.settings.index', 'ws.settings.plan', 'ws.masters.index', 'ws.setup', 'ws.brokers.index', 'ws.iam.create'] as $r) {
            $this->actingAs($admin)->get(route($r))->assertOk();
        }
        foreach (['organisation', 'sales', 'disclaimers', 'ids', 'calendar'] as $tab) {
            $this->actingAs($admin)->get(route('ws.settings.index', ['tab' => $tab]))->assertOk();
        }
        foreach (['stages', 'facilities', 'documents', 'sros'] as $t) {
            $this->actingAs($admin)->get(route('ws.masters.index', ['type' => $t]))->assertOk();
        }
        $app = $this->appAdmin();
        foreach (['app.plans.index', 'app.plans.create', 'app.subscriptions.index', 'app.iam.index', 'app.iam.tenant-users', 'app.audit.index', 'app.settings.templates', 'app.settings.ai-usage', 'app.masters.index'] as $r) {
            $this->actingAs($app)->get(route($r))->assertOk();
        }
        foreach (['organisation', 'payment', 'smtp', 'ai', 'storage', 'social', 'security'] as $tab) {
            $this->actingAs($app)->get(route('app.settings.index', ['tab' => $tab]))->assertOk();
        }
        $this->actingAs($app)->get(route('app.subscriptions.show', $tenant))->assertOk();
        $this->actingAs($app)->get(route('app.subscriptions.index', ['export' => 'xlsx']))->assertOk();
    }
}
