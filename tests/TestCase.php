<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Plan;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Registers a tenant through the real sign-up service and returns its Admin. */
    protected function makeTenantAdmin(string $email): User
    {
        $admin = app(TenantProvisioner::class)->register([
            'business_name' => 'Tenant '.$email, 'name' => 'Admin', 'email' => $email,
            'phone' => '9800000000', 'password' => 'Str0ng!Passw0rd',
        ], Plan::query()->where('code', 'growth')->firstOrFail(), 'monthly');

        return $admin->refresh();
    }

    protected function makeUser(User $admin, Role $role, string $email): User
    {
        $user = new User(['name' => ucfirst($role->value), 'email' => $email, 'password' => 'Str0ng!Passw0rd']);
        $user->tenant_id = $admin->tenant_id;
        $user->role = $role;
        $user->status = 'active';
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
