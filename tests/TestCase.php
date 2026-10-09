<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IdGenerator;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Database\Seeders\MasterSeeder;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\SroSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = PlatformSeeder::class;

    protected function afterRefreshingDatabase(): void
    {
        // App masters + SROs once per run (inside the migrated DB, outside the per-test transaction).
        if (! \App\Models\StageMaster::withoutGlobalScopes()->exists()) {
            $this->seed(MasterSeeder::class);
            $this->seed(SroSeeder::class);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app(Tenancy::class)->set(null);
    }

    protected function tearDown(): void
    {
        app(Tenancy::class)->set(null);
        parent::tearDown();
    }

    /** The Tenancy singleton outlives a request in tests; reset it after every HTTP call like a real request would. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            app(Tenancy::class)->set(null);
        }
    }

    /** Each actingAs starts a fresh session (AuthenticateSession ties a session to one password hash). */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        $this->flushSession();
        app('auth')->forgetGuards();

        return parent::actingAs($user, $guard ?? ($user instanceof Customer ? 'customer' : 'web'));
    }

    /** Create a tenant with its Tenant Admin; optionally copy the App masters. */
    protected function makeTenant(string $name = 'Acme Promoters', bool $masters = false, string $plan = 'professional'): array
    {
        $plan = Plan::where('slug', $plan)->firstOrFail();
        Plan::query()->update(['is_trial_default' => false]);
        $plan->update(['is_trial_default' => true]);
        [$tenant, $admin] = TenantProvisioner::create([
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::random(5).'@example.com',
            'mobile' => '9876543210',
            'password' => 'Secret@123',
            'city' => 'Thanjavur',
            'district' => 'Thanjavur',
        ]);
        if ($masters) {
            TenantProvisioner::copyAppMasters($tenant);
        }

        return [$tenant->fresh(), $admin->fresh()];
    }

    protected function makeUser(Tenant $tenant, string $baseRole, array $attrs = []): User
    {
        return app(Tenancy::class)->run($tenant, fn () => User::create(array_merge([
            'tenant_id' => $tenant->id,
            'role_id' => Role::systemRole($baseRole, $tenant->id)->id,
            'user_code' => IdGenerator::next($tenant->id, 'user'),
            'name' => ucfirst(str_replace('_', ' ', $baseRole)).' User',
            'email' => $baseRole.'-'.Str::random(6).'@example.com',
            'password' => 'Secret@123',
        ], $attrs))->fresh());
    }

    protected function appAdmin(): User
    {
        return User::withoutGlobalScopes()->whereNull('tenant_id')->whereHas('role', fn ($q) => $q->where('base_role', 'app_admin'))->firstOrFail();
    }

    protected function appManager(): User
    {
        return User::create([
            'tenant_id' => null,
            'role_id' => Role::whereNull('tenant_id')->where('base_role', 'app_manager')->value('id'),
            'name' => 'App Manager',
            'email' => 'manager-'.Str::random(5).'@vector7.in',
            'password' => 'Secret@123',
        ])->fresh();
    }

    protected function makeCustomer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Kumar Customer',
            'email' => 'cust-'.Str::random(6).'@example.com',
            'mobile' => '9123456780',
            'password' => 'Secret@123',
        ], $attrs))->fresh();
    }
}
