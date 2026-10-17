<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoginEvent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TopupPack;
use App\Models\User;
use App\Services\IdGenerator;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Direct launch from the project phase, Property Lister role and plans, App Support role, security monitoring. */
class ListersSupportAndSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    private function registerLister(array $o = []): User
    {
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $this->flushSession();
        $this->post(route('lister.register.store'), array_merge([
            'admin_name' => 'Selvi Raman', 'name' => '', 'email' => 'selvi-'.Str::random(4).'@example.com', 'mobile' => '9876543211', 'city' => 'Thanjavur',
            'password' => 'Lister@2026x', 'password_confirmation' => 'Lister@2026x', 'terms' => 1,
        ], $o))->assertSessionHasNoErrors()->assertRedirect(route('ws.listings.create'));

        return auth('web')->user()->fresh();
    }

    private function appSupport(): User
    {
        return User::create([
            'tenant_id' => null, 'role_id' => Role::whereNull('tenant_id')->where('base_role', 'app_support')->value('id'),
            'name' => 'Priya Support', 'email' => 'support-'.Str::random(5).'@vector7.in', 'password' => 'Secret@123',
        ])->fresh();
    }

    // ---------------------------------------------------------------- direct launch

    public function test_project_in_progress_can_skip_to_launch(): void
    {
        [$tenant, $admin] = $this->makeTenant('Skip Co', true);
        $p = app(Tenancy::class)->run($tenant, fn () => Project::create([
            'tenant_id' => $tenant->id, 'project_code' => IdGenerator::next($tenant->id, 'project'), 'name' => 'Green Acres', 'location' => 'Vallam',
            'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'pin' => '613403', 'size_acres' => 3, 'status' => 'in_progress', 'approval_type' => 'Village',
            'survey_numbers' => '12/1', 'patta_numbers' => '501',
        ]));
        $this->actingAs($admin)->get(route('ws.launch.index'))->assertOk()->assertSee('Launch a layout directly')->assertSee('Still in the project phase')->assertSee('Green Acres');
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $this->actingAs($sales)->post(route('ws.launch.skip', $p), ['approval_type' => 'Town'])->assertForbidden();

        $this->actingAs($admin)->post(route('ws.launch.skip', $p), ['approval_type' => 'Town', 'approval_authority' => 'DTCP', 'approval_number' => 'LP/9/2026', 'reason' => 'Approved offline'])
            ->assertRedirect(route('ws.launch.show', $p));
        $p->refresh();
        $this->assertSame('ready_to_launch', $p->status);
        $this->assertSame('DTCP', $p->approval_authority);
        $this->actingAs($admin)->get(route('ws.launch.show', $p))->assertOk();
        // Already past the project phase → cannot skip again.
        $this->actingAs($admin)->post(route('ws.launch.skip', $p), ['approval_type' => 'Town'])->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'project_phase_skipped']);
    }

    // ---------------------------------------------------------------- property listers

    public function test_lister_signs_up_from_marketplace_and_gets_lister_workspace(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('lister.login'), false)->assertSee(route('lister.register'), false);
        $this->get(route('lister.register'))->assertOk()->assertSee('Lister Basic')->assertSee('List your property');

        $user = $this->registerLister();
        $tenant = $user->tenant;
        $this->assertTrue($tenant->isLister());
        $this->assertSame('property_lister', $user->baseRole());
        $this->assertSame('lister-basic', $tenant->subscription->plan->slug);
        $this->assertSame('trial', $tenant->subscription->status);
        $this->assertFalse(Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('base_role', 'tenant_admin')->exists());
        $this->assertTrue(LoginEvent::where('portal', 'lister')->where('outcome', 'success')->exists());

        $this->actingAs($user)->get(route('ws.dashboard'))->assertOk()->assertSee('My listings')->assertSee('Post a property')->assertSee('Enquiries &amp; call-backs', false)->assertDontSee('Launch &amp; plots', false);
        $this->actingAs($user)->get(route('ws.listings.create'))->assertOk();
        $this->actingAs($user)->get(route('ws.leads.index'))->assertOk();
        $this->actingAs($user)->get(route('ws.projects.index'))->assertForbidden();
        $this->actingAs($user)->get(route('ws.iam.index'))->assertForbidden();
        $this->actingAs($user)->get(route('ws.settings.index'))->assertRedirect(route('ws.settings.plan'));
        $this->actingAs($user)->get(route('ws.settings.plan'))->assertOk()->assertSee('Lister Plus')->assertDontSee('Enterprise')->assertDontSee('1 extra project');

        // Listings and leads work as for promoters; the dashboard tracks them.
        $listing = ListingsMarketplaceTest::makeListing($tenant, ['title' => 'Selvi 2 BHK flat', 'views' => 40]);
        $this->from($listing->publicUrl())->post(route('market.contact.callback', ['listing', $listing->id]), [
            'name' => 'Ravi', 'mobile' => '9876512000', 'preferred_mode' => 'call', 'preferred_slot' => 'morning', 'consent' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('ws.dashboard'))->assertOk()->assertSee('Selvi 2 BHK flat')->assertSee('Ravi')->assertSee('Open call-backs');

        // Only lister plans and lister top-ups.
        $this->actingAs($user)->post(route('ws.settings.plan.upgrade', Plan::where('slug', 'growth')->first()))->assertNotFound();
        $this->actingAs($user)->post(route('ws.settings.topup', TopupPack::where('key', 'users')->first()), ['quantity' => 1])->assertNotFound();
        $this->actingAs($user)->post(route('ws.settings.plan.upgrade', Plan::where('slug', 'lister-plus')->first()))->assertSessionHas('ok');
    }

    public function test_lister_sign_in_pages_are_separate(): void
    {
        $lister = $this->registerLister(['email' => 'lister@example.com']);
        $this->post(route('staff.logout'))->assertRedirect(route('lister.login'));
        [, $promoter] = $this->makeTenant('Promo Co');

        $this->post(route('staff.login.post'), ['email' => 'lister@example.com', 'password' => 'Lister@2026x'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->post(route('lister.login.post'), ['email' => $promoter->email, 'password' => 'Secret@123'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->post(route('lister.login.post'), ['email' => 'lister@example.com', 'password' => 'Lister@2026x'])->assertRedirect(route('ws.dashboard'));
        $this->assertAuthenticatedAs($lister, 'web');
        $this->assertTrue(LoginEvent::where('email', 'lister@example.com')->where('portal', 'promoter')->where('outcome', 'blocked')->exists());
    }

    public function test_app_admin_designs_lister_plans(): void
    {
        $admin = $this->appAdmin();
        $this->actingAs($admin)->get(route('app.plans.index'))->assertOk()->assertSee('Property lister plans')->assertSee('Lister Pro');
        $this->actingAs($admin)->get(route('app.plans.create', ['audience' => 'lister']))->assertOk()->assertSee('New property lister plan')->assertDontSee('Enabled modules');
        $limits = ['storage_mb' => 1024, 'projects' => 0, 'plots' => 0, 'users' => 1, 'listings' => 60, 'images_per_listing' => 15, 'ai_credits' => 30,
            'users_tenant_admin' => -1, 'users_tenant_manager' => -1, 'users_tenant_sales' => -1, 'users_tenant_account' => -1, 'users_support' => -1];
        $this->actingAs($admin)->post(route('app.plans.store'), [
            'audience' => 'lister', 'name' => 'Lister Agent', 'price' => 699, 'billing_cycle' => 'monthly', 'trial_days' => 7, 'is_active' => 1, 'is_trial_default' => 1, 'limits' => $limits,
        ])->assertSessionHasNoErrors()->assertRedirect(route('app.plans.index'));
        $plan = Plan::where('name', 'Lister Agent')->firstOrFail();
        $this->assertSame('lister', $plan->audience);
        $this->assertSame(['leads', 'ai'], $plan->modules);
        $this->assertSame(60, $plan->limit('listings'));
        // Trial default is per audience: the promoter default stays.
        $this->assertTrue(Plan::where('audience', 'promoter')->where('is_trial_default', true)->exists());
        $this->assertFalse(Plan::where('slug', 'lister-basic')->first()->is_trial_default);
        $this->assertSame('Lister Agent', $this->registerLister()->tenant->subscription->plan->name);

        $this->get(route('market.pricing', ['for' => 'listings']))->assertOk()->assertSee('Lister Agent')->assertDontSee('Enterprise');
    }

    // ---------------------------------------------------------------- App Support + security

    public function test_app_support_role_reaches_support_tools_only(): void
    {
        $support = $this->appSupport();
        $this->post(route('app.login.post'), ['email' => $support->email, 'password' => 'Secret@123'])->assertRedirect();
        $this->actingAs($support)->get(route('app.dashboard'))->assertOk()->assertSee('Open tickets')->assertSee('Failed sign-ins today')->assertDontSee('Revenue');
        foreach (['app.tickets.index', 'app.enquiries.index', 'app.chats.index', 'app.services.index', 'app.security.index'] as $r) {
            $this->actingAs($support)->get(route($r))->assertOk();
        }
        foreach (['app.plans.index', 'app.subscriptions.index', 'app.settings.index', 'app.iam.index', 'app.audit.index'] as $r) {
            $this->actingAs($support)->get(route($r))->assertForbidden();
        }
        $this->assertFalse($support->hasPerm('services.update')); // the services catalogue stays with App Admin / Manager
        $this->assertTrue($this->appAdmin()->hasPerm('app_security.update'));
        $this->assertFalse($this->appManager()->hasPerm('app_security.view'));
        // App Admin can give someone the role.
        $this->actingAs($this->appAdmin())->get(route('app.iam.index'))->assertOk()->assertSee('App Support');
    }

    public function test_sign_in_attempts_are_monitored_and_sessions_can_be_ended(): void
    {
        [$tenant, $admin] = $this->makeTenant('Watch Co');
        $customer = $this->makeCustomer(['email' => 'buyer@example.com']);
        $this->post(route('staff.login.post'), ['email' => 'nobody@example.com', 'password' => 'x'])->assertSessionHasErrors('email');
        foreach (range(1, 5) as $i) {
            $this->post(route('customer.login'), ['email' => 'buyer@example.com', 'password' => 'wrong-'.$i]);
        }
        $this->assertTrue($customer->fresh()->isLocked());
        $this->assertSame(4, LoginEvent::where('email', 'buyer@example.com')->where('outcome', 'failed')->count());
        $this->assertSame(1, LoginEvent::where('email', 'buyer@example.com')->where('outcome', 'locked')->count());
        $this->assertSame('Unknown email', LoginEvent::where('email', 'nobody@example.com')->value('reason'));
        $this->post(route('staff.login.post'), ['email' => $admin->email, 'password' => 'Secret@123'])->assertRedirect();
        $ok = LoginEvent::where('email', $admin->email)->where('outcome', 'success')->firstOrFail();
        $this->assertSame('promoter', $ok->portal);
        $this->assertSame($tenant->id, $ok->tenant_id);

        // A live session row for the promoter admin (tests use the array session driver).
        DB::table('sessions')->insert(['id' => 'sess-watch', 'user_id' => $admin->id, 'ip_address' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0', 'payload' => '', 'last_activity' => now()->timestamp]);
        $ok->update(['session_id' => 'sess-watch', 'ip' => '203.0.113.5']);

        $support = $this->appSupport();
        $this->actingAs($support)->get(route('app.security.index'))->assertOk()->assertSee($admin->email)->assertSee('203.0.113.9')->assertSee('Chrome · Windows')->assertSee('changed');
        $this->actingAs($support)->get(route('app.security.index', ['tab' => 'activity', 'outcome' => 'problem']))->assertOk()->assertSee('buyer@example.com')->assertSee('Account locked');
        $this->actingAs($support)->get(route('app.security.index', ['tab' => 'ips']))->assertOk()->assertSee('127.0.0.1');
        $this->actingAs($support)->get(route('app.security.index', ['tab' => 'activity', 'export' => 'xlsx']))->assertOk();
        $this->actingAs($support)->get(route('app.security.index', ['tab' => 'locked']))->assertOk()->assertSee('buyer@example.com');

        $this->actingAs($support)->delete(route('app.security.end', 'sess-watch'))->assertSessionHas('ok');
        $this->assertFalse(DB::table('sessions')->where('id', 'sess-watch')->exists());
        $this->actingAs($support)->post(route('app.security.unlock'), ['type' => 'customer', 'id' => $customer->id])->assertSessionHas('ok');
        $this->assertFalse($customer->fresh()->isLocked());
        $this->assertDatabaseHas('audit_logs', ['action' => 'session_ended']);
    }
}
