<?php

namespace Tests\Feature;

use App\Exceptions\PlanLimitException;
use App\Models\ContactUnlock;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Plan;
use App\Models\Project;
use App\Models\SubscriptionInvoice;
use App\Models\TenantTopup;
use App\Models\TopupPack;
use App\Services\AppSettings;
use App\Services\ContactCharges;
use App\Services\IdGenerator;
use App\Services\PlanLimiter;
use App\Services\PlotImporter;
use App\Services\TopupService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Plans v2: Starter / Growth / Enterprise on five plan items, top-ups, paid contact view, free call-back requests. */
class PlansTopupsAndContactChargesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    private function razorpay(): void
    {
        AppSettings::set('payment.gateway', 'razorpay');
        AppSettings::set('payment.razorpay_key_id', 'rzp_test_x');
        AppSettings::set('payment.razorpay_key_secret', 'secret123');
        AppSettings::set('payment.razorpay_webhook_secret', 'whsec');
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'plink_123', 'short_url' => 'https://rzp.io/i/abc'], 200)]);
    }

    private function project($tenant): Project
    {
        return app(Tenancy::class)->run($tenant, fn () => Project::create([
            'tenant_id' => $tenant->id, 'project_code' => IdGenerator::next($tenant->id, 'project'), 'name' => 'Limit Nagar', 'location' => 'Vallam',
            'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'size_acres' => 2, 'status' => 'planning', 'approval_type' => 'Village',
            'survey_numbers' => '12/1', 'patta_numbers' => '501', 'pin' => '613403',
        ]));
    }

    private function rows(array $nos): array
    {
        return array_map(fn ($n) => ['plot_no' => (string) $n, 'patta_number' => '501', 'size_sqft' => 1200, 'facing' => 'East', 'rate_per_sqft' => 1000, 'status' => 'available', 'map_polygon' => null], $nos);
    }

    public function test_plans_are_starter_growth_enterprise_with_five_plan_items(): void
    {
        $this->assertSame(['Starter', 'Growth', 'Enterprise'], Plan::orderBy('sort')->pluck('name')->all());
        $starter = Plan::where('slug', 'starter')->first();
        foreach (PlanLimiter::PLAN_ITEMS as $key) {
            $this->assertNotNull($starter->limits->firstWhere('key', $key), $key);
        }
        $this->assertSame(5, $starter->limit('listings'));
        $this->assertSame(-1, $starter->limit('users_tenant_sales'));

        $admin = $this->appAdmin();
        $this->actingAs($admin)->get(route('app.plans.index'))->assertOk()->assertSee('Plots')->assertSee('Users (all roles)')->assertSee('Top-up packs');
        $this->actingAs($admin)->get(route('app.plans.edit', $starter))->assertOk()->assertSee('Plan items')->assertSee('Optional caps per role');
        $limits = collect(PlanLimiter::LIMIT_LABELS)->map(fn () => -1)->all();
        $limits = array_merge($limits, ['storage_mb' => 2048, 'projects' => 3, 'plots' => 300, 'users' => 6, 'listings' => 8, 'images_per_listing' => 12, 'ai_credits' => 50]);
        $this->actingAs($admin)->put(route('app.plans.update', $starter), [
            'name' => 'Starter', 'price' => 2499, 'billing_cycle' => 'monthly', 'trial_days' => 14, 'is_active' => 1, 'modules' => ['leads'], 'limits' => $limits,
        ])->assertSessionHasNoErrors()->assertRedirect(route('app.plans.index'));
        $this->assertSame(300, $starter->fresh()->limit('plots'));
        $this->assertSame(6, $starter->fresh()->limit('users'));
    }

    public function test_plot_limit_and_granted_top_up(): void
    {
        [$tenant, $admin] = $this->makeTenant('Plot Co', false, 'starter');
        Plan::where('slug', 'starter')->first()->limits()->where('key', 'plots')->update(['value' => 3]);
        $project = $this->project($tenant);
        app(Tenancy::class)->run($tenant, fn () => PlotImporter::import($project, $this->rows([1, 2, 3]), $admin->id));
        try {
            app(Tenancy::class)->run($tenant, fn () => PlotImporter::import($project, $this->rows([4]), $admin->id));
            $this->fail('Plot limit not enforced');
        } catch (PlanLimitException $e) {
            $this->assertStringContainsString('3 plots', $e->getMessage());
            $this->assertStringContainsString('top-up', $e->getMessage());
        }
        // Re-importing existing plot numbers is not "adding".
        app(Tenancy::class)->run($tenant, fn () => PlotImporter::import($project, $this->rows([1, 2]), $admin->id));

        // App team grants 10 plots → allowed.
        $this->actingAs($this->appAdmin())->post(route('app.topups.grant', $tenant), ['key' => 'plots', 'units' => 10, 'note' => 'Launch offer'])->assertSessionHas('ok');
        $this->assertSame(13, PlanLimiter::limit($tenant->fresh(), 'plots'));
        app(Tenancy::class)->run($tenant, fn () => PlotImporter::import($project, $this->rows([4, 5]), $admin->id));
        $this->assertSame(5, PlanLimiter::used($tenant, 'plots'));
        $usage = PlanLimiter::usage($tenant->fresh());
        $this->assertSame(10, $usage['plots']['topup']);
        $this->actingAs($admin)->get(route('ws.settings.plan'))->assertOk()->assertSee('incl. +10 top-up')->assertSee('+10 plots');
    }

    public function test_tenant_buys_top_up_and_app_marks_it_paid(): void
    {
        [$tenant, $admin] = $this->makeTenant('Topup Co', false, 'starter');
        $tenant->subscription->update(['status' => 'active', 'starts_on' => today(), 'ends_on' => today()->addDays(44)]);
        $pack = TopupPack::where('key', 'storage_mb')->first(); // 5 GB, ₹249 / month
        $this->actingAs($admin)->post(route('ws.settings.topup', $pack), ['quantity' => 2])->assertSessionHas('ok');

        $inv = SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('kind', 'topup')->firstOrFail();
        $this->assertNull($inv->plan_id);
        $this->assertEquals(249 * 2 * 2, (float) $inv->amount); // 45 days left → 2 months
        $this->assertEquals(round(249 * 4 * 1.18, 2), (float) $inv->total);
        $topup = TenantTopup::withoutGlobalScopes()->find($inv->tenant_topup_id);
        $this->assertSame('pending', $topup->status);
        $this->assertSame(1024, PlanLimiter::limit($tenant->fresh(), 'storage_mb'));

        $app = $this->appAdmin();
        $this->actingAs($app)->get(route('app.subscriptions.show', $tenant))->assertOk()->assertSee('Top-up: 5 GB extra storage × 2')->assertSee('Grant a free top-up');
        $this->actingAs($app)->post(route('app.invoices.paid', $inv))->assertSessionHas('ok');
        $this->assertTrue($topup->fresh()->isCurrent());
        $this->assertSame(1024 + 10240, PlanLimiter::limit($tenant->fresh(), 'storage_mb'));
        $this->assertSame('active', $tenant->fresh()->subscription->status); // plan untouched
        $this->actingAs($app)->get(route('app.invoices.show', $inv))->assertOk();
        $this->actingAs($app)->get(route('app.topups.index'))->assertOk()->assertSee('+10 GB storage');

        // Ends with the plan period.
        $this->travelTo(today()->addDays(46));
        $this->assertSame(1024, PlanLimiter::limit($tenant->fresh(), 'storage_mb'));
    }

    public function test_top_up_needs_an_active_plan_and_app_manages_packs(): void
    {
        [$tenant, $admin] = $this->makeTenant('Lapsed Co', false, 'starter');
        $tenant->subscription->update(['status' => 'expired', 'ends_on' => today()->subDay()]);
        $this->actingAs($admin)->post(route('ws.settings.topup', TopupPack::first()), ['quantity' => 1])->assertSessionHas('error');

        $app = $this->appAdmin();
        $this->actingAs($app)->post(route('app.topups.store'), ['key' => 'plots', 'name' => '500 extra plots', 'units' => 500, 'price' => 999, 'is_active' => 1])->assertSessionHas('ok');
        $pack = TopupPack::where('name', '500 extra plots')->firstOrFail();
        $this->actingAs($app)->put(route('app.topups.update', $pack), ['key' => 'plots', 'name' => '500 extra plots', 'units' => 500, 'price' => 899, 'is_active' => 0])->assertSessionHas('ok');
        $this->assertFalse($pack->fresh()->is_active);
        $this->actingAs($app)->delete(route('app.topups.destroy', $pack))->assertSessionHas('ok');
        $this->assertNull($pack->fresh());
    }

    public function test_five_free_listings_survive_a_lapsed_subscription(): void
    {
        [$tenant] = $this->makeTenant('Free Co', false, 'starter');
        $tenant->subscription->update(['status' => 'expired', 'ends_on' => today()->subDays(20)]);
        $tenant = $tenant->fresh();
        foreach (range(1, 5) as $i) {
            ListingsMarketplaceTest::makeListing($tenant, ['slug' => 'free-'.$i], false);
        }
        $this->assertSame(5, PlanLimiter::used($tenant, 'listings'));
        $this->expectException(PlanLimitException::class);
        PlanLimiter::ensure($tenant, 'listings');
    }

    public function test_listing_publish_allowed_within_free_listings_when_expired(): void
    {
        [$tenant] = $this->makeTenant('Free2 Co', false, 'starter');
        $tenant->subscription->update(['status' => 'expired', 'ends_on' => today()->subDays(20)]);
        PlanLimiter::ensure($tenant->fresh(), 'listings'); // 0 of 5 used → fine
        $this->expectException(PlanLimitException::class);
        PlanLimiter::ensure($tenant->fresh(), 'projects'); // everything else needs a running plan
    }

    public function test_contact_is_free_without_a_payment_gateway(): void
    {
        [$tenant] = $this->makeTenant('Gate Co');
        $l = ListingsMarketplaceTest::makeListing($tenant);
        $this->assertNull(ContactCharges::amount('listing', $l));
        $this->get($l->publicUrl())->assertOk()->assertSee('Show number')->assertSee('Request a call back')->assertDontSee('Sign in &amp; pay', false);
        $this->postJson(route('market.contact.reveal', ['listing', $l->id]), ['name' => 'Asha', 'mobile' => '9876501234', 'consent' => 1, 'mode' => 'show'])
            ->assertOk()->assertJsonPath('number', '9840012345');
    }

    public function test_buyer_pays_to_view_contact_and_app_owner_sees_revenue(): void
    {
        $this->razorpay();
        [$tenant] = $this->makeTenant('Paid Co');
        $l = ListingsMarketplaceTest::makeListing($tenant);
        $this->assertSame(49.0, ContactCharges::amount('listing', $l));

        // Guest: masked number, sign-in-and-pay link, free call-back form; the reveal endpoint refuses.
        $this->get($l->publicUrl())->assertOk()->assertSee('Sign in &amp; pay ₹49', false)->assertSee('Request a call back')->assertDontSee('9840012345');
        $this->postJson(route('market.contact.reveal', ['listing', $l->id]), ['name' => 'Asha', 'mobile' => '9876501234', 'consent' => 1, 'mode' => 'show'])
            ->assertStatus(402)->assertJsonPath('pay', true);
        $this->get(route('market.contact.unlock.start', ['listing', $l->id]))->assertRedirect(route('login'));

        // Signed-in buyer pays on the hosted page.
        $buyer = $this->makeCustomer(['mobile' => '9876501234']);
        $this->actingAs($buyer, 'customer')->get($l->publicUrl())->assertSee('Pay ₹49 &amp; view number', false);
        $this->actingAs($buyer, 'customer')->post(route('market.contact.unlock', ['listing', $l->id]))->assertRedirect('https://rzp.io/i/abc');
        $u = ContactUnlock::firstOrFail();
        $this->assertSame('due', $u->status);
        $this->assertEquals(49, (float) $u->total);
        $this->assertEquals(41.53, (float) $u->amount);
        $this->assertSame($tenant->id, $u->tenant_id);
        Http::assertSent(fn ($r) => $r['reference_id'] === $u->number && $r['amount'] === 4900);

        // Signed callback from Razorpay → paid, unlocked for 30 days.
        $q = ['razorpay_payment_id' => 'pay_1', 'razorpay_payment_link_id' => 'plink_123', 'razorpay_payment_link_reference_id' => $u->number, 'razorpay_payment_link_status' => 'paid'];
        $q['razorpay_signature'] = hash_hmac('sha256', implode('|', [$q['razorpay_payment_link_id'], $q['razorpay_payment_link_reference_id'], $q['razorpay_payment_link_status'], $q['razorpay_payment_id']]), 'secret123');
        $this->actingAs($buyer, 'customer')->get(route('market.contact.unlock.verify', $u).'?'.http_build_query($q))->assertRedirect($l->publicUrl().'#contact')->assertSessionHas('ok');
        $u->refresh();
        $this->assertSame('paid', $u->status);
        $this->assertTrue($u->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->actingAs($buyer, 'customer')->postJson(route('market.contact.reveal', ['listing', $l->id]), ['mode' => 'show'])->assertOk()->assertJsonPath('number', '9840012345');
        $this->actingAs($buyer, 'customer')->get($l->publicUrl())->assertSee('Contact unlocked until');
        $this->actingAs($buyer, 'customer')->get(route('account.enquiries'))->assertOk()->assertSee($u->number);
        // The promoter gets the lead; the money is the App owner's.
        $this->assertTrue(Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('mobile', '9876501234')->exists());

        // Another buyer still has to pay; another property too.
        $other = $this->makeCustomer(['mobile' => '9876505555']);
        $this->actingAs($other, 'customer')->postJson(route('market.contact.reveal', ['listing', $l->id]), ['mode' => 'show'])->assertStatus(402);

        $app = $this->appAdmin();
        $this->actingAs($app)->get(route('app.contact-charges.index'))->assertOk()->assertSee($u->number)->assertSee('41.53');
        $this->actingAs($app)->get(route('app.contact-charges.index', ['export' => 'xlsx']))->assertOk();
    }

    public function test_webhook_marks_contact_view_paid(): void
    {
        $this->razorpay();
        [$tenant] = $this->makeTenant('Hook Co');
        $l = ListingsMarketplaceTest::makeListing($tenant);
        $buyer = $this->makeCustomer();
        $u = ContactCharges::start($buyer, 'listing', $l);
        $u->update(['gateway_order_id' => 'plink_999']);
        $body = json_encode(['event' => 'payment_link.paid', 'payload' => ['payment_link' => ['entity' => ['id' => 'plink_999', 'reference_id' => $u->number]], 'payment' => ['entity' => ['id' => 'pay_9']]]]);
        $this->call('POST', route('webhooks.payment', 'razorpay'), [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
        $this->assertSame('paid', $u->fresh()->status);
        $this->assertNotNull(ContactCharges::unlocked($buyer, 'listing', $l->id));
    }

    public function test_app_admin_sets_contact_charges_per_type_and_layouts(): void
    {
        $this->razorpay();
        [$tenant] = $this->makeTenant('Price Co');
        $flat = ListingsMarketplaceTest::makeListing($tenant);
        $plot = ListingsMarketplaceTest::makeListing($tenant, ['type' => 'plot', 'slug' => 'a-plot', 'bhk' => null, 'type_details' => ['facing' => 'East']]);
        $data = ['contact_charge_enabled' => 1, 'contact_charge_listing' => 29, 'contact_charge_layout' => 0, 'contact_validity_days' => 7, 'listings_free' => 6, 'listings_image_max_mb' => 4, 'contact_charge_type_plot' => 19];
        $this->actingAs($this->appAdmin())->put(route('app.contact-charges.update'), $data)->assertSessionHas('ok');
        $this->assertSame(29.0, ContactCharges::amount('listing', $flat));
        $this->assertSame(19.0, ContactCharges::amount('listing', $plot));
        $this->assertNull(ContactCharges::amount('project', new Project(['tenant_id' => $tenant->id])));
        $this->assertSame(7, ContactCharges::validityDays());
        $this->assertSame(6, PlanLimiter::freeListings());
        $this->assertSame(4096, \App\Services\PropertyMedia::maxKb());

        $this->actingAs($this->appAdmin())->put(route('app.contact-charges.update'), ['contact_charge_enabled' => 0] + $data)->assertSessionHas('ok');
        $this->assertNull(ContactCharges::amount('listing', $flat));
    }

    public function test_free_call_back_request_lands_in_owner_leads_with_mode_and_time(): void
    {
        $this->razorpay(); // works even when viewing the number is paid
        [$tenant, $admin] = $this->makeTenant('Callback Co');
        $l = ListingsMarketplaceTest::makeListing($tenant);
        $date = today()->addDays(2)->toDateString();
        $this->from($l->publicUrl())->post(route('market.contact.callback', ['listing', $l->id]), [
            'name' => 'Meena', 'mobile' => '9876512345', 'preferred_mode' => 'whatsapp', 'preferred_slot' => 'evening', 'preferred_date' => $date,
            'message' => 'Is the price negotiable?', 'consent' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');

        $lead = Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('mobile', '9876512345')->firstOrFail();
        $this->assertSame('callback', $lead->source);
        $this->assertSame('whatsapp', $lead->preferred_mode);
        $this->assertStringContainsString('Evening', $lead->preferred_time);
        $f = LeadFollowup::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame($date.' 16:00', $f->due_at->format('Y-m-d H:i'));
        $this->assertSame($f->due_at->toDateTimeString(), $lead->next_follow_up_at->toDateTimeString());

        $this->actingAs($admin)->get(route('ws.leads.show', $lead))->assertOk()->assertSee('9876512345')->assertSee('WhatsApp')->assertSee('Evening (4–8 pm)');
        $this->actingAs($admin)->get(route('ws.leads.index', ['source' => 'callback']))->assertOk()->assertSee('Meena');

        // Email mode needs an email address.
        $this->from($l->publicUrl())->post(route('market.contact.callback', ['listing', $l->id]), [
            'name' => 'Meena', 'mobile' => '9876512345', 'preferred_mode' => 'email', 'preferred_slot' => 'any', 'consent' => 1,
        ])->assertSessionHasErrors('email');
    }
}
