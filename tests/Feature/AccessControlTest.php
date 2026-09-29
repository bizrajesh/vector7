<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_boundaries(): void
    {
        $admin = $this->makeTenantAdmin('admin@example.test');
        $sales = $this->makeUser($admin, Role::Sales, 'sales@example.test');
        $holder = $this->makeUser($admin, Role::Shareholder, 'holder@example.test');

        $this->actingAs($sales)->get('/app/settings')->assertForbidden();
        $this->actingAs($sales)->get('/app/users')->assertForbidden();
        $this->actingAs($sales)->get('/app/shares')->assertForbidden();
        $this->actingAs($holder)->get('/app')->assertForbidden();
        $this->actingAs($admin)->get('/platform')->assertNotFound();
    }

    public function test_no_share_trading_routes_exist(): void
    {
        $uris = collect(app('router')->getRoutes())->map->uri()->implode(' ');

        $this->assertStringNotContainsString('shares/buy', $uris);
        $this->assertStringNotContainsString('shares/sell', $uris);
        $this->assertStringNotContainsString('shares/transfer', $uris);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password']);
        }

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])->assertStatus(429);
    }

    public function test_unknown_route_is_a_real_404(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound();
    }
}
