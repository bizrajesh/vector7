<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_staff_login_and_logout(): void
    {
        [, $admin] = $this->makeTenant();
        $this->post('/workspace/login', ['email' => $admin->email, 'password' => 'Secret@123'])->assertRedirect(route('ws.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->post('/workspace/logout')->assertRedirect(route('staff.login'));
        $this->assertGuest('web');
    }

    public function test_app_admin_uses_separate_app_login(): void
    {
        // The promoter workspace sign-in (linked from the marketplace) refuses App users.
        $this->post('/workspace/login', ['email' => 'admin@vector7.in', 'password' => 'ChangeMe@2026'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
        // The separate App sign-in works for App users and is never linked from the marketplace.
        $this->get('/app/login')->assertOk()->assertSee('vector7 App sign in')->assertSee('noindex', false);
        $this->post('/app/login', ['email' => 'admin@vector7.in', 'password' => 'ChangeMe@2026'])->assertRedirect(route('app.dashboard'));
        $this->post('/workspace/logout')->assertRedirect(route('app.login'));
        // Tenant users cannot use the App sign-in.
        [, $admin] = $this->makeTenant('Login Sep Co');
        $this->post('/app/login', ['email' => $admin->email, 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
        // Guests sent to the App workspace land on the App sign-in; the marketplace never links to it.
        $this->get('/app')->assertRedirect(route('app.login'));
        $this->get('/')->assertOk()->assertDontSee('/app/login');
        $this->get('/robots.txt')->assertDontSee('/app');
    }

    public function test_customer_cannot_use_staff_login_and_vice_versa(): void
    {
        $c = $this->makeCustomer();
        $this->post('/workspace/login', ['email' => $c->email, 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        [, $admin] = $this->makeTenant();
        $this->post('/login', ['email' => $admin->email, 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $c->email, 'password' => 'Secret@123'])->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($c, 'customer');
    }

    public function test_account_locks_after_five_wrong_passwords_for_fifteen_minutes(): void
    {
        [, $admin] = $this->makeTenant();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/workspace/login', ['email' => $admin->email, 'password' => 'wrong-pass']);
        }
        $admin->refresh();
        $this->assertTrue($admin->isLocked());
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($admin->locked_until), 1);
        // even the right password is refused while locked
        $this->post('/workspace/login', ['email' => $admin->email, 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->travel(16)->minutes();
        $this->post('/workspace/login', ['email' => $admin->email, 'password' => 'Secret@123'])->assertRedirect();
        $this->assertAuthenticated('web');
    }

    public function test_password_rule_8_to_32_characters_no_edge_spaces(): void
    {
        $base = ['name' => 'A', 'email' => 'new@example.com', 'mobile' => '9876543210', 'terms' => '1'];
        $this->post('/register', $base + ['password' => 'short7!', 'password_confirmation' => 'short7!'])->assertSessionHasErrors('password');
        $long = str_repeat('a', 33);
        $this->post('/register', $base + ['password' => $long, 'password_confirmation' => $long])->assertSessionHasErrors('password');
        $this->post('/register', $base + ['password' => ' spaced123', 'password_confirmation' => ' spaced123'])->assertSessionHasErrors('password');
        $ok = str_repeat('b', 32);
        $this->post('/register', $base + ['password' => $ok, 'password_confirmation' => $ok])->assertSessionHasNoErrors();
        $this->assertAuthenticated('customer');
    }

    public function test_no_otp_two_factor_setting_is_off(): void
    {
        $this->assertSame('0', \App\Services\AppSettings::get('security.two_factor'));
    }

    public function test_forgot_password_sends_single_use_link(): void
    {
        [, $admin] = $this->makeTenant();
        \Illuminate\Support\Facades\Mail::fake();
        $this->post('/workspace/forgot-password', ['email' => $admin->email])->assertSessionHas('ok');
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TemplateMail::class, fn ($m) => str_contains($m->bodyText, 'reset-password/'));
        $token = \Illuminate\Support\Facades\Password::broker('users')->createToken($admin);
        $this->post('/workspace/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'NewPass@123', 'password_confirmation' => 'NewPass@123'])->assertRedirect(route('staff.login'));
        // second use of the same token fails
        $this->post('/workspace/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'Other@1234', 'password_confirmation' => 'Other@1234'])->assertSessionHasErrors('email');
    }

    public function test_login_is_audit_logged(): void
    {
        [, $admin] = $this->makeTenant();
        $this->post('/workspace/login', ['email' => $admin->email, 'password' => 'Secret@123']);
        $this->assertTrue(AuditLog::where('action', 'login')->where('auditable_id', $admin->id)->exists());
    }

    public function test_security_headers_present(): void
    {
        $r = $this->get('/workspace/login');
        $r->assertHeader('X-Frame-Options', 'DENY');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('noindex', $r->headers->get('X-Robots-Tag'));
    }
}
