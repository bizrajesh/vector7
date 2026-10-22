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

    public function test_sign_ups_are_closed_on_a_new_install(): void
    {
        \App\Models\Setting::where('key', 'like', 'signup.%')->delete();
        \Illuminate\Support\Facades\Cache::forget('app_settings');
        foreach (array_keys(\App\Support\Signups::KINDS) as $k) {
            $this->assertFalse(\App\Support\Signups::open($k));
        }
        $this->get(route('register'))->assertForbidden();
        $this->get(route('lister.register'))->assertForbidden();
        $this->get(route('signup'))->assertForbidden();
        // Sign-in stays available on the marketplace.
        $this->get('/')->assertOk()->assertSee(route('login'), false)->assertSee(route('lister.login'), false)->assertSee(route('staff.login'), false);
        $this->get(route('market.pricing'))->assertOk()->assertDontSee('href="'.route('signup').'"', false);
    }

    public function test_app_admin_opens_each_sign_up_separately(): void
    {
        $this->setSignups(false);
        $admin = $this->appAdmin();
        $admin->forceFill(['password' => Hash::make('Secret@123')])->save();
        $admin = $admin->fresh();
        $this->actingAs($admin)->get(route('app.settings.index', ['tab' => 'signups']))->assertOk()->assertSee('Who can create an account on the marketplace');
        $this->actingAs($admin)->put(route('app.settings.update', 'signups'), ['signup__buyer' => '1', 'current_password' => 'Secret@123'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(\App\Support\Signups::open('buyer'));
        $this->assertFalse(\App\Support\Signups::open('lister'));
        $this->assertFalse(\App\Support\Signups::open('promoter'));
        auth('web')->logout();
        $this->get(route('register'))->assertOk();
        $this->get(route('lister.register'))->assertForbidden();
        $this->get(route('signup'))->assertForbidden();
        $this->get(route('login'))->assertOk()->assertSee('Create a free account');
        $this->get(route('lister.login'))->assertOk()->assertDontSee('Create a lister account');
    }

    public function test_sign_ups_can_be_closed(): void
    {
        $this->setSignups(false);
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
