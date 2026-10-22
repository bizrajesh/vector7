<?php

namespace Tests\Feature;

use App\Http\Middleware\PreviewGate;
use App\Models\LoginEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Live-test setup: preview gate in front of the site, sign-ups closed, no patta / survey numbers on the marketplace. */
class PreviewGateAndSignupsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    private function gate(): void
    {
        config(['vector7.preview_gate' => ['enabled' => true, 'email' => 'tester@example.com', 'password_hash' => Hash::make('Preview#2026'), 'remember_days' => 30]]);
    }

    public function test_gate_blocks_every_page_until_preview_sign_in(): void
    {
        $this->gate();
        foreach (['/', '/buy', '/login', '/workspace/login', '/app/login', '/list-property/login'] as $url) {
            $this->get($url)->assertRedirect(route('preview.login'));
        }
        $this->getJson('/search?q=flat')->assertStatus(401);
        $this->get('/up')->assertOk();
        $this->post('/webhooks/payment/razorpay', [])->assertStatus(400); // still reaches the webhook (bad signature)

        $this->get('/buy');
        $this->get(route('preview.login'))->assertOk()->assertSee('vector7 is in testing')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post(route('preview.login.post'), ['email' => 'tester@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertTrue(LoginEvent::where('portal', 'preview')->where('outcome', 'failed')->exists());

        $r = $this->post(route('preview.login.post'), ['email' => 'TESTER@example.com', 'password' => 'Preview#2026']);
        $r->assertRedirect(url('/buy'))->assertCookie(PreviewGate::COOKIE);
        $this->withCookie(PreviewGate::COOKIE, PreviewGate::token())->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->withCookie(PreviewGate::COOKIE, 'forged')->get('/')->assertRedirect(route('preview.login'));

        // Changing the password signs every tester out.
        $old = PreviewGate::token();
        config(['vector7.preview_gate.password_hash' => Hash::make('Another#2026')]);
        $this->withCookie(PreviewGate::COOKIE, $old)->get('/')->assertRedirect(route('preview.login'));
    }

    public function test_gate_is_off_by_default(): void
    {
        $this->get('/')->assertOk();
        $this->get(route('preview.login'))->assertRedirect(route('home'));
    }

    public function test_sign_ups_can_be_closed(): void
    {
        config(['vector7.registration_open' => false]);
        foreach (['register', 'lister.register', 'signup'] as $r) {
            $this->get(route($r))->assertForbidden()->assertSee('New accounts are not open yet');
        }
        $this->post(route('customer.register'), ['name' => 'X', 'email' => 'x@example.com', 'mobile' => '9876543210', 'password' => 'Secret@123', 'password_confirmation' => 'Secret@123', 'terms' => 1])->assertForbidden();
        $this->post(route('lister.register.store'), ['admin_name' => 'X', 'email' => 'y@example.com', 'mobile' => '9876543210', 'password' => 'Secret@123', 'password_confirmation' => 'Secret@123', 'terms' => 1])->assertForbidden();
        $this->post(route('signup.store'), ['type' => 'individual', 'name' => 'X', 'email' => 'z@example.com', 'mobile' => '9876543210', 'password' => 'Secret@123', 'password_confirmation' => 'Secret@123', 'terms' => 1])->assertForbidden();
        $this->assertDatabaseMissing('customers', ['email' => 'x@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'y@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'z@example.com']);
        $this->get(route('login'))->assertOk()->assertDontSee('Create a free account');
        $this->get(route('staff.login'))->assertOk()->assertDontSee('Create a workspace');
        $this->get('/')->assertOk()->assertDontSee('href="'.route('lister.register').'"', false);
        // Existing accounts still sign in.
        [, $admin] = $this->makeTenant('Closed Co');
        $this->post(route('staff.login.post'), ['email' => $admin->email, 'password' => 'Secret@123'])->assertRedirect();
        $this->assertAuthenticated('web');
    }

    public function test_marketplace_never_shows_patta_or_survey_numbers(): void
    {
        [$tenant] = $this->makeTenant('Patta Co');
        $l = ListingsMarketplaceTest::makeListing($tenant, ['type' => 'plot', 'slug' => 'patta-plot', 'patta_number' => 'PT-77341', 'survey_number' => 'SV-99812', 'bhk' => null, 'type_details' => ['facing' => 'East']]);
        $this->get($l->publicUrl())->assertOk()->assertDontSee('PT-77341')->assertDontSee('SV-99812')->assertDontSee('Patta number');

        $plot = \App\Models\Plot::withoutGlobalScopes()->with('project')->whereHas('project', fn ($q) => $q->withoutGlobalScopes()->where('status', 'launched'))->first();
        if ($plot) {
            $plot->forceFill(['patta_number' => 'PT-55123'])->saveQuietly();
            $this->get($plot->project->publicUrl())->assertOk()->assertDontSee('PT-55123');
            $this->get($plot->publicUrl())->assertOk()->assertDontSee('PT-55123')->assertDontSee('Patta number');
        }
        $this->assertNull((new \App\Models\Plot(['patta_number' => 'X1', 'status' => 'available']))->tooltipData()['patta']);
        $this->assertSame('X1', (new \App\Models\Plot(['patta_number' => 'X1', 'status' => 'available']))->tooltipData(true)['patta']);
    }
}
