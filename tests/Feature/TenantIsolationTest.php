<?php

namespace Tests\Feature;

use App\Models\NotificationGroup;
use App\Models\User;
use App\Support\Tenancy;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_tenant_user_cannot_read_or_change_another_tenants_users_by_guessing_ids(): void
    {
        [$a, $adminA] = $this->makeTenant('Tenant A');
        [$b, $adminB] = $this->makeTenant('Tenant B');
        $userB = $this->makeUser($b, 'tenant_sales');

        $this->actingAs($adminA)->get(route('ws.iam.edit', $userB))->assertNotFound();
        $this->actingAs($adminA)->put(route('ws.iam.update', $userB), ['name' => 'Hacked', 'email' => $userB->email, 'role_id' => $userB->role_id])->assertNotFound();
        $this->actingAs($adminA)->delete(route('ws.iam.destroy', $userB))->assertNotFound();
        $this->assertSame('Tenant sales User', $userB->fresh()->name);

        $page = $this->actingAs($adminA)->get(route('ws.iam.index'))->assertOk();
        $page->assertDontSee($userB->email);
    }

    public function test_tenant_scope_filters_queries_and_blocks_cross_tenant_writes(): void
    {
        [$a] = $this->makeTenant('Tenant A');
        [$b] = $this->makeTenant('Tenant B');
        app(Tenancy::class)->set($a);
        $this->assertSame([$a->id], User::pluck('tenant_id')->unique()->values()->all());
        $this->assertSame(0, NotificationGroup::where('tenant_id', $b->id)->count());
        try {
            NotificationGroup::create(['tenant_id' => $b->id, 'name' => 'Injected']);
            $this->fail('cross-tenant write should abort');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        app(Tenancy::class)->set(null);
    }

    public function test_tenant_user_cannot_open_app_workspace_and_app_manager_cannot_open_iam_or_settings(): void
    {
        [, $admin] = $this->makeTenant();
        $this->actingAs($admin)->get(route('app.dashboard'))->assertForbidden();
        $mgr = $this->appManager();
        $this->actingAs($mgr)->get(route('app.plans.index'))->assertOk();
        $this->actingAs($mgr)->get(route('app.iam.index'))->assertForbidden();
        $this->actingAs($mgr)->get(route('app.settings.index'))->assertForbidden();
        $this->actingAs($this->appAdmin())->get(route('app.settings.index'))->assertOk();
    }

    public function test_role_permissions_enforced_on_routes(): void
    {
        [$tenant] = $this->makeTenant();
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $account = $this->makeUser($tenant, 'tenant_account');
        $this->actingAs($sales)->get(route('ws.settings.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('ws.iam.index'))->assertForbidden();
        $this->actingAs($account)->get(route('ws.iam.index'))->assertForbidden();
        $support = $this->makeUser($tenant, 'support');
        $this->actingAs($support)->get(route('ws.iam.index'))->assertOk();
        $this->actingAs($support)->delete(route('ws.iam.destroy', $sales))->assertForbidden();
    }

    public function test_custom_role_combines_only_existing_permissions(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $this->actingAs($admin)->post(route('ws.roles.store'), ['name' => 'Site Engineer', 'base_role' => 'tenant_manager', 'permissions' => ['projects.view', 'tracking.update', 'app_settings.update', 'fake.perm']])->assertSessionHas('ok');
        $role = \App\Models\Role::where('name', 'Site Engineer')->first();
        $this->assertEqualsCanonicalizing(['projects.view', 'tracking.update'], $role->permissionKeys());
    }
}
