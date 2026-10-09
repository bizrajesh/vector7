<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IamTest extends TestCase
{
    public function test_tenant_admin_generates_password_shown_once_and_forced_change(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $r = $this->actingAs($admin)->post(route('ws.iam.generate', $sales), ['must_change' => '1']);
        $r->assertSessionHas('generated');
        $plain = session('generated')['password'];
        $this->assertSame(12, strlen($plain));
        $this->assertMatchesRegularExpression('/[A-Z]/', $plain);
        $this->assertMatchesRegularExpression('/[a-z]/', $plain);
        $this->assertMatchesRegularExpression('/[0-9]/', $plain);
        $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $plain);
        $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $plain);
        $sales->refresh();
        $this->assertTrue(Hash::check($plain, $sales->password));
        $this->assertTrue($sales->must_change_password);
        // audit row names who/for whom but never the password
        $log = AuditLog::where('action', 'password_generated')->latest('id')->first();
        $this->assertSame($sales->email, $log->after['for']);
        $this->assertStringNotContainsString($plain, json_encode($log->after));

        // the user must set a new password before using the app
        auth('web')->logout();
        $this->post('/workspace/login', ['email' => $sales->email, 'password' => $plain])->assertRedirect(route('staff.force-password'));
        $this->get(route('ws.dashboard'))->assertRedirect(route('staff.force-password'));
        $this->post(route('staff.force-password.update'), ['password' => 'MyOwn@Pass1', 'password_confirmation' => 'MyOwn@Pass1'])->assertRedirect();
        $this->assertFalse($sales->fresh()->must_change_password);
        $this->get(route('ws.dashboard'))->assertOk();
    }

    public function test_generate_password_can_email_user(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant();
        $u = $this->makeUser($tenant, 'tenant_manager');
        $this->actingAs($admin)->post(route('ws.iam.generate', $u), ['must_change' => '0', 'email_user' => '1']);
        Mail::assertSent(\App\Mail\TemplateMail::class, fn ($m) => $m->hasTo($u->email) && str_contains($m->bodyText, session('generated')['password']));
        $this->assertFalse($u->fresh()->must_change_password);
    }

    public function test_generate_password_permissions_per_role(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        [$other, $otherAdmin] = $this->makeTenant('Other Promoters');
        $support = $this->makeUser($tenant, 'support');
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $customer = $this->makeCustomer();

        // Support → tenant users except Tenant Admin
        $this->assertTrue($support->canGeneratePasswordFor($sales));
        $this->assertFalse($support->canGeneratePasswordFor($admin));
        // Tenant Admin → own tenant only; never App users, other tenants or customers
        $this->assertTrue($admin->canGeneratePasswordFor($support));
        $this->assertFalse($admin->canGeneratePasswordFor($otherAdmin));
        $this->assertFalse($admin->canGeneratePasswordFor($this->appAdmin()));
        $this->assertFalse($admin->canGeneratePasswordFor($customer));
        // Sales cannot generate at all
        $this->assertFalse($sales->canGeneratePasswordFor($support));
        // App Admin → anyone
        $this->assertTrue($this->appAdmin()->canGeneratePasswordFor($otherAdmin));
        $this->assertTrue($this->appAdmin()->canGeneratePasswordFor($customer));

        // HTTP: support blocked for tenant admin; other tenant's user is a 404
        $this->actingAs($support)->post(route('ws.iam.generate', $admin))->assertForbidden();
        $this->actingAs($admin)->post(route('ws.iam.generate', $otherAdmin))->assertNotFound();
        $this->actingAs($sales)->post(route('ws.iam.generate', $support))->assertForbidden();
        // App Admin can do customers
        $this->actingAs($this->appAdmin())->post(route('app.customers.generate', $customer))->assertSessionHas('generated');
    }

    public function test_bulk_generate_downloads_excel_and_logs_out_other_sessions(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $a = $this->makeUser($tenant, 'tenant_sales');
        $b = $this->makeUser($tenant, 'tenant_account');
        \DB::table('sessions')->insert(['id' => 'sess-a', 'user_id' => $a->id, 'payload' => 'x', 'last_activity' => time()]);
        $r = $this->actingAs($admin)->post(route('ws.iam.bulk'), ['ids' => [$a->id, $b->id], 'must_change' => '1']);
        $r->assertOk();
        $this->assertStringContainsString('spreadsheetml', $r->headers->get('Content-Type'));
        $this->assertFalse(\DB::table('sessions')->where('id', 'sess-a')->exists());
        $this->assertTrue($a->fresh()->must_change_password);
    }

    public function test_tenant_admin_creates_user_within_plan_limits(): void
    {
        [$tenant, $admin] = $this->makeTenant('Small Co', false, 'starter'); // starter: 2 sales users
        $sales = \App\Models\Role::systemRole('tenant_sales', $tenant->id);
        $mk = fn ($i) => $this->actingAs($admin)->post(route('ws.iam.store'), ['name' => "S$i", 'email' => "s$i@x.com", 'role_id' => $sales->id, 'is_active' => 1, 'password_mode' => 'generate', 'must_change' => 1]);
        $mk(1)->assertSessionHas('generated');
        $mk(2)->assertSessionHas('generated');
        $mk(3)->assertSessionHas('error');
        $this->assertStringContainsString('Upgrade your plan', session('error'));
        $this->assertSame(2, User::where('tenant_id', $tenant->id)->where('role_id', $sales->id)->count());
    }

    public function test_tenant_admin_unlocks_account(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $u = $this->makeUser($tenant, 'tenant_sales', ['locked_until' => now()->addMinutes(10)]);
        $this->actingAs($admin)->post(route('ws.iam.unlock', $u))->assertSessionHas('ok');
        $this->assertFalse($u->fresh()->isLocked());
    }

    public function test_csv_bulk_import_users(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant();
        $csv = "full_name,email,mobile,role,notification_groups\nRavi Kumar,ravi@example.com,9876543210,Manager,Management;Sales Team\nLata,lata@example.com,9876500000,Accountant,Accounts";
        $this->actingAs($admin)->post(route('ws.iam.import'), ['csv' => $csv])->assertSessionHas('ok');
        $ravi = User::where('email', 'ravi@example.com')->first();
        $this->assertSame('tenant_manager', $ravi->role->base_role);
        $this->assertEqualsCanonicalizing(['Management', 'Sales Team'], $ravi->groups->pluck('name')->all());
        $this->assertSame('tenant_account', User::where('email', 'lata@example.com')->first()->role->base_role);
    }

    public function test_user_changes_own_password(): void
    {
        [, $admin] = $this->makeTenant();
        $this->actingAs($admin)->put(route('profile.password'), ['current_password' => 'wrong', 'password' => 'Brand@New1', 'password_confirmation' => 'Brand@New1'])->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->put(route('profile.password'), ['current_password' => 'Secret@123', 'password' => 'Brand@New1', 'password_confirmation' => 'Brand@New1'])->assertSessionHas('ok');
        $this->assertTrue(Hash::check('Brand@New1', $admin->fresh()->password));
    }
}
