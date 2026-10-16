<?php

namespace Tests\Feature;

use App\Models\AiSearchLog;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Plot;
use App\Models\Project;
use App\Models\SavedSearch;
use App\Services\AppSettings;
use App\Services\IdGenerator;
use App\Services\LeadService;
use App\Services\PropertyDetails;
use App\Services\PropertyMedia;
use App\Services\PropertySearch;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Feature Update 01 — listings, quick launch, contact block, leads, marketplace search (spec 17.13). */
class ListingsMarketplaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    private function images(int $n = 3): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("photo$i.jpg", 800, 600), range(1, $n));
    }

    /** A published listing with 3 photos and a contact block. */
    public static function makeListing($tenant, array $o = [], bool $withImages = true): Listing
    {
        return app(Tenancy::class)->run($tenant, function () use ($tenant, $o, $withImages) {
            $code = IdGenerator::next($tenant->id, 'listing');
            $l = Listing::create(array_merge([
                'tenant_id' => $tenant->id, 'listing_code' => $code, 'type' => 'flat', 'purpose' => 'sale',
                'title' => 'Sunny 2 BHK Flat', 'slug' => 'sunny-2-bhk-'.strtolower($code), 'description' => 'Bright flat close to a school and the bus stand.',
                'city' => 'Thanjavur', 'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'lat' => 10.787, 'lng' => 79.137,
                'price' => 4500000, 'area_value' => 1100, 'area_unit' => 'sqft', 'area_sqft' => 1100, 'bhk' => 2, 'facing' => 'East',
                'type_details' => ['bhk' => 2, 'built_up_sqft' => 1100, 'facing' => 'East'], 'features_text' => 'Lift Car parking',
                'status' => 'published', 'published_at' => now(),
            ], $o));
            PropertyDetails::saveContact($l, ['contact_name' => 'Ravi', 'contact_mobile' => '9840012345', 'contact_whatsapp' => '9840099999', 'contact_email' => 'ravi@example.com',
                'allow_call' => true, 'allow_whatsapp' => true, 'allow_email' => true]);
            if ($withImages) {
                PropertyMedia::addImages($l, array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg", 640, 480), [1, 2, 3]));
            }

            return $l->fresh();
        });
    }

    private function contactPayload(array $o = []): array
    {
        return array_merge(['_token' => csrf_token(), 'name' => 'Asha', 'mobile' => '9876501234', 'consent' => '1'], $o);
    }

    // ------------------------------------------------------------------ 17.1 direct launch

    public function test_direct_launch_creates_bookable_launched_project_without_estimate_or_tracking(): void
    {
        [$tenant, $admin] = $this->makeTenant('Quick Co');
        $csv = "plot_no,patta_number,size_sqft,facing,rate_per_sqft,status\n1,501,1200,East,1000,Available\n2,502,1500,North,1000,Available";
        $this->actingAs($admin)->post(route('ws.quick-launch.store'), [
            'name' => 'Sunrise Nagar', 'location' => 'Vallam', 'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'pin' => '613403',
            'approval_type' => 'Village', 'approval_authority' => 'DTCP', 'approval_number' => 'LP/12/2026', 'survey_numbers' => '12/1', 'patta_numbers' => '501',
            'size_acres' => 2, 'promo_text' => 'Approved plots near Vallam.', 'layout' => UploadedFile::fake()->image('layout.png', 800, 600),
            'images' => $this->images(), 'plots_csv' => $csv,
            'contact_name' => 'Ravi', 'contact_mobile' => '9840012345', 'allow_call' => 1, 'allow_whatsapp' => 1, 'allow_email' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $p = Project::withoutGlobalScopes()->where('name', 'Sunrise Nagar')->firstOrFail();
        $this->assertSame('launched', $p->status);
        $this->assertSame('direct', $p->launch_mode);
        $this->assertSame(2, $p->plots()->withoutGlobalScopes()->count());
        $this->assertSame(3, $p->images()->withoutGlobalScopes()->count());
        $this->actingAs($admin)->get(route('ws.estimate.edit', $p))->assertNotFound();
        $this->actingAs($admin)->get(route('ws.tracking.show', $p))->assertNotFound();
        // Public page and booking work as for a normal launch.
        $this->get($p->publicUrl())->assertOk()->assertSee('Sunrise Nagar')->assertSee('LP/12/2026');
        $plot = Plot::withoutGlobalScopes()->where('project_id', $p->id)->where('plot_no', '1')->first();
        $sale = \App\Services\SalesService::book($plot, $this->makeCustomer(), 'marketplace', null, '1.1.1.1');
        $this->assertSame('booked', $plot->fresh()->status);
        $this->assertNotNull($sale);
    }

    public function test_quick_launch_needs_three_photos_and_valid_plots(): void
    {
        [, $admin] = $this->makeTenant('Quick Bad');
        $this->actingAs($admin)->post(route('ws.quick-launch.store'), [
            'name' => 'X', 'location' => 'Vallam', 'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'pin' => '613403', 'approval_type' => 'Village',
            'survey_numbers' => '1', 'patta_numbers' => '1', 'size_acres' => 1, 'promo_text' => 'x', 'layout' => UploadedFile::fake()->image('l.png'),
            'images' => $this->images(2), 'plots_csv' => "plot_no,patta_number,size_sqft,facing,rate_per_sqft,status\n1,1,1000,East,1000,Available",
            'contact_name' => 'R', 'contact_mobile' => '9840012345', 'allow_call' => 1,
        ])->assertSessionHasErrors('images');
        $this->assertSame(0, Project::withoutGlobalScopes()->where('name', 'X')->count());
    }

    // ------------------------------------------------------------------ 17.2 listings

    public function test_listing_publish_rules_per_type(): void
    {
        [$tenant, $admin] = $this->makeTenant('List Co');
        $base = ['purpose' => 'sale', 'title' => 'Farm with borewell', 'city' => 'Budalur', 'district' => 'Thanjavur', 'state' => 'Tamil Nadu', 'price' => 2500000,
            'contact_name' => 'Ravi', 'contact_mobile' => '9840012345', 'allow_call' => 1];
        // Farm land needs a patta or survey number and the extent.
        $this->actingAs($admin)->post(route('ws.listings.store'), ['type' => 'farm_land'] + $base)->assertSessionHasErrors(['patta_number', 'details.extent']);
        // Flats need BHK and built-up area; rent is allowed.
        $this->actingAs($admin)->post(route('ws.listings.store'), ['type' => 'flat', 'purpose' => 'rent'] + $base)->assertSessionHasErrors(['details.bhk', 'details.built_up_sqft']);
        // Plots and farm land cannot be rented.
        $this->actingAs($admin)->post(route('ws.listings.store'), ['type' => 'plot', 'purpose' => 'rent'] + $base)->assertSessionHasErrors('purpose');
        // Offer price must be lower than the price.
        $this->actingAs($admin)->post(route('ws.listings.store'), ['type' => 'house', 'offer_price' => 3000000, 'offer_valid_till' => today()->addDay()->toDateString(),
            'details' => ['bhk' => 3, 'built_up_sqft' => 1500, 'plot_area_sqft' => 2000]] + $base)->assertSessionHasErrors('offer_price');

        $this->actingAs($admin)->post(route('ws.listings.store'), ['type' => 'plot', 'title' => 'Corner plot', 'patta_number' => '777',
            'details' => ['area' => 5, 'area_unit' => 'cents', 'facing' => 'East']] + $base)->assertSessionHasNoErrors();
        $l = Listing::withoutGlobalScopes()->where('title', 'Corner plot')->firstOrFail();
        $this->assertSame('draft', $l->status);
        $this->assertEqualsWithDelta(5 * 435.6, (float) $l->area_sqft, 0.1);
        // Publishing needs 3 photos.
        $this->actingAs($admin)->post(route('ws.listings.status', $l), ['status' => 'published'])->assertSessionHas('error');
        $this->assertSame('draft', $l->fresh()->status);
        app(Tenancy::class)->run($tenant, fn () => PropertyMedia::addImages($l, $this->images()));
        $this->actingAs($admin)->post(route('ws.listings.status', $l), ['status' => 'published'])->assertSessionHas('ok');
        $l->refresh();
        $this->assertSame('published', $l->status);
        $this->get($l->publicUrl())->assertOk()->assertSee('Corner plot');
        $this->assertStringContainsString('/buy/plots/budalur/', $l->publicUrl());
    }

    public function test_sales_and_support_cannot_edit_listings_or_quick_launch(): void
    {
        [$tenant] = $this->makeTenant('Perm Co');
        $l = self::makeListing($tenant);
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $support = $this->makeUser($tenant, 'support');
        $manager = $this->makeUser($tenant, 'tenant_manager');
        $this->actingAs($sales)->get(route('ws.listings.index'))->assertOk();
        $this->actingAs($sales)->get(route('ws.listings.create'))->assertForbidden();
        $this->actingAs($sales)->get(route('ws.listings.edit', $l))->assertForbidden();
        $this->actingAs($sales)->post(route('ws.listings.status', $l), ['status' => 'sold'])->assertForbidden();
        $this->actingAs($sales)->post(route('ws.quick-launch.store'), [])->assertForbidden();
        $this->actingAs($support)->get(route('ws.listings.edit', $l))->assertForbidden();
        $this->actingAs($support)->get(route('ws.quick-launch.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('ws.listings.edit', $l))->assertOk();
        $this->assertSame('published', $l->fresh()->status);
    }

    public function test_offer_price_shows_only_while_valid(): void
    {
        [$tenant] = $this->makeTenant('Offer Co');
        $l = self::makeListing($tenant, ['offer_price' => 4000000, 'offer_text' => 'Diwali offer', 'offer_valid_till' => today()->subDay()]);
        $this->assertFalse($l->offerIsActive());
        $this->assertEquals(4500000, $l->currentPrice());
        $this->get($l->publicUrl())->assertOk()->assertDontSee('Diwali offer')->assertDontSee('<del', false);

        $l->update(['offer_valid_till' => today()]);
        $this->assertTrue($l->fresh()->offerIsActive());
        $this->assertEquals(4000000, $l->fresh()->currentPrice());
        $this->get($l->publicUrl())->assertOk()->assertSee('Diwali offer')->assertSee('<del', false);
        // Search filters use the current price.
        $items = PropertySearch::search(PropertySearch::clean(['budget_max' => 4200000]))['items'];
        $this->assertContains('L:'.$l->id, $items->pluck('key')->all());
    }

    // ------------------------------------------------------------------ 17.5 contact block

    public function test_masked_number_reveal_creates_lead_and_is_rate_limited(): void
    {
        [$tenant] = $this->makeTenant('Reveal Co');
        $l = self::makeListing($tenant);
        RateLimiter::clear('rv:127.0.0.1');
        $this->get($l->publicUrl())->assertOk()->assertSee('98XXX XX345')->assertDontSee('9840012345');
        // Name, mobile and consent are required the first time.
        $url = route('market.contact.reveal', ['listing', $l->id]);
        $this->postJson($url, ['mode' => 'show', 'name' => 'Asha', 'mobile' => '9876501234'])->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->postJson($url, ['mode' => 'show', 'name' => 'Asha', 'mobile' => '9876501234', 'consent' => 1])->assertOk()->assertJson(['number' => '9840012345', 'tel' => 'tel:+919840012345']);
        $lead = Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('mobile', '9876501234')->firstOrFail();
        $this->assertSame('show_number', $lead->source);
        $this->assertTrue((bool) $lead->consent);
        $this->assertSame($l->id, $lead->interests()->withoutGlobalScopes()->first()->listing_id);
        // Session remembers the visitor: no form the second time, and the lead is merged, not duplicated.
        $this->postJson($url, ['mode' => 'call'])->assertOk();
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        // Rate limit: 5 per minute per visitor.
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($url, ['mode' => 'show'])->assertOk();
        }
        $this->postJson($url, ['mode' => 'show'])->assertStatus(429);
    }

    public function test_whatsapp_link_has_correct_number_and_prefilled_text(): void
    {
        [$tenant] = $this->makeTenant('WA Co');
        $l = self::makeListing($tenant);
        RateLimiter::clear('rv:127.0.0.1');
        $r = $this->postJson(route('market.contact.reveal', ['listing', $l->id]), ['mode' => 'whatsapp', 'name' => 'Asha', 'mobile' => '9876501234', 'consent' => 1])->assertOk();
        $text = "Hi, I'm interested in {$l->title} ({$l->listing_code}) on vector7.in – {$l->publicUrl()}";
        $this->assertSame('https://wa.me/919840099999?text='.rawurlencode($text), $r->json('whatsapp'));
        $this->assertSame('whatsapp_click', Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->source);
        // A mode the owner switched off is refused.
        $l->contact->update(['allow_whatsapp' => false]);
        $this->postJson(route('market.contact.reveal', ['listing', $l->id]), ['mode' => 'whatsapp'])->assertStatus(422);
    }

    public function test_email_enquiry_creates_lead_and_ai_source_is_kept(): void
    {
        [$tenant] = $this->makeTenant('Mail Co');
        $l = self::makeListing($tenant);
        $this->post(route('market.contact.email', ['listing', $l->id]), ['name' => 'Bala', 'email' => 'bala@example.com', 'mobile' => '9876512345', 'message' => 'Is it available?', 'consent' => 1, 'src' => 'ai'])
            ->assertSessionHas('ok');
        $lead = Lead::withoutGlobalScopes()->where('email', 'bala@example.com')->firstOrFail();
        $this->assertSame('ai_search', $lead->source);
    }

    // ------------------------------------------------------------------ 17.6 leads

    public function test_lead_duplicate_merge_by_mobile_or_email(): void
    {
        [$tenant] = $this->makeTenant('Dup Co');
        $l = self::makeListing($tenant);
        $a = LeadService::capture($tenant->id, 'enquiry', ['name' => 'Kavi', 'mobile' => '+91 98765 43210', 'email' => 'kavi@example.com'], ['listing_id' => $l->id]);
        $b = LeadService::capture($tenant->id, 'call_click', ['name' => 'Kavitha', 'mobile' => '9876543210'], ['listing_id' => $l->id]);
        $c = LeadService::capture($tenant->id, 'chatbot', ['name' => 'K', 'email' => 'KAVI@example.com']);
        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->id, $c->id);
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame('9876543210', $a->fresh()->mobile);
        // A different tenant gets its own lead.
        [$other] = $this->makeTenant('Dup Other');
        $d = LeadService::capture($other->id, 'enquiry', ['name' => 'Kavi', 'mobile' => '9876543210']);
        $this->assertNotSame($a->id, $d->id);
    }

    public function test_sales_sees_only_own_and_unassigned_leads(): void
    {
        [$tenant, $admin] = $this->makeTenant('Vis Co');
        $s1 = $this->makeUser($tenant, 'tenant_sales', ['name' => 'Sales One']);
        $s2 = $this->makeUser($tenant, 'tenant_sales', ['name' => 'Sales Two']);
        $mine = LeadService::capture($tenant->id, 'manual', ['name' => 'Mine Lead', 'mobile' => '9000000001']);
        $theirs = LeadService::capture($tenant->id, 'manual', ['name' => 'Their Lead', 'mobile' => '9000000002']);
        $free = LeadService::capture($tenant->id, 'manual', ['name' => 'Free Lead', 'mobile' => '9000000003']);
        app(Tenancy::class)->run($tenant, function () use ($mine, $theirs, $free, $s1, $s2) {
            LeadService::assign($mine, $s1);
            LeadService::assign($theirs, $s2);
            LeadService::assign($free, null);
        });
        $this->actingAs($s1)->get(route('ws.leads.index', ['view' => 'list']))->assertOk()->assertSee('Mine Lead')->assertSee('Free Lead')->assertDontSee('Their Lead');
        $this->actingAs($s1)->get(route('ws.leads.show', $theirs))->assertNotFound();
        $this->actingAs($admin)->get(route('ws.leads.index', ['view' => 'list']))->assertOk()->assertSee('Their Lead');
    }

    public function test_followup_reminder_emails(): void
    {
        [$tenant] = $this->makeTenant('Rem Co');
        $s = $this->makeUser($tenant, 'tenant_sales', ['email' => 'rem-sales@example.com']);
        $lead = LeadService::capture($tenant->id, 'manual', ['name' => 'Due Lead', 'mobile' => '9000000009']);
        app(Tenancy::class)->run($tenant, function () use ($lead, $s) {
            LeadService::assign($lead, $s);
            LeadService::scheduleFollowup($lead, now()->subHour(), 'follow_up', 'Call back', $s->id);
        });
        Mail::fake();
        \App\Services\LeadReminders::morning();
        Mail::assertQueued(\App\Mail\TemplateMail::class, fn ($m) => $m->hasTo('rem-sales@example.com') && str_contains($m->mailSubject, 'follow-up'));
    }

    // ------------------------------------------------------------------ 17.7 marketplace

    public function test_listing_urls_categories_and_gone(): void
    {
        [$tenant] = $this->makeTenant('Url Co');
        $l = self::makeListing($tenant, ['title' => 'Lake View Villa', 'type' => 'villa', 'slug' => 'lake-view-villa-x1', 'type_details' => ['bhk' => 3, 'built_up_sqft' => 1800]]);
        $this->assertSame(url('/buy/villas/thanjavur/lake-view-villa-x1'), $l->publicUrl());
        $this->get($l->publicUrl())->assertOk()->assertSee('RealEstateListing')->assertSee('GeoCoordinates')->assertSee('Lake View Villa');
        $this->assertSame(1, $l->fresh()->views);
        // Wrong segments 301 to the canonical URL.
        $this->get('/buy/flats/trichy/lake-view-villa-x1')->assertRedirect($l->publicUrl())->assertStatus(301);
        // Category pages.
        $this->get('/buy/villas')->assertOk()->assertSee('Villas for sale')->assertSee('Lake View Villa');
        $this->get('/buy/villas/thanjavur')->assertOk()->assertSee('Villas for sale in Thanjavur');
        $this->get('/buy/villas/nowhere-town')->assertNotFound();
        $this->get('/rent')->assertOk()->assertDontSee('Lake View Villa');
        // Sold → 410 and gone from search & sitemap.
        $l->update(['status' => 'sold']);
        $this->get($l->publicUrl())->assertStatus(410)->assertSee('no longer available');
        $this->get('/buy/villas')->assertDontSee('Lake View Villa');
        $this->get(route('sitemap'))->assertDontSee('lake-view-villa-x1');
        // Hidden by admin → 404.
        $l->update(['status' => 'published', 'hidden_by_admin' => true]);
        $this->get($l->publicUrl())->assertNotFound();
    }

    public function test_sitemap_lists_live_listings_and_category_pages(): void
    {
        [$tenant] = $this->makeTenant('Map Co');
        $l = self::makeListing($tenant);
        $this->get(route('sitemap'))->assertOk()->assertSee($l->publicUrl(), false)->assertSee(url('/buy/flats/thanjavur'), false);
    }

    public function test_search_filters_layouts_and_listings(): void
    {
        [$tenant] = $this->makeTenant('Search Co');
        $flat = self::makeListing($tenant);
        $shop = self::makeListing($tenant, ['type' => 'commercial', 'purpose' => 'rent', 'title' => 'Main road shop', 'slug' => 'main-road-shop-1', 'price' => 25000, 'bhk' => null, 'area_sqft' => 400,
            'type_details' => ['built_up_sqft' => 400]]);
        $p = LaunchTest::readyProject($tenant);
        $p->update(['status' => 'launched', 'launched_at' => now()]);
        app(Tenancy::class)->run($tenant, fn () => Plot::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'plot_no' => '7', 'patta_number' => '7', 'size_sqft' => 1200, 'facing' => 'East', 'rate_per_sqft' => 1000]));

        $keys = fn ($f) => PropertySearch::search(PropertySearch::clean($f))['items']->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['L:'.$flat->id, 'L:'.$shop->id, 'P:'.$p->id], $keys([]));
        $this->assertSame(['L:'.$shop->id], $keys(['purpose' => 'rent']));
        $this->assertSame(['P:'.$p->id], $keys(['types' => ['layout'], 'budget_max' => 1500000]));
        $this->assertSame(['L:'.$flat->id], $keys(['bhk_min' => 2]));
        $this->assertSame([], $keys(['places' => ['Madurai']]));
        $this->get(route('market.search', ['type' => ['flat'], 'view' => 'list']))->assertOk()->assertSee('Sunny 2 BHK Flat')->assertDontSee('Main road shop');
        $this->get(route('market.search', ['view' => 'map']))->assertOk()->assertSee('search-pins');
    }

    public function test_shortlist_compare_and_saved_search_alerts(): void
    {
        [$tenant] = $this->makeTenant('Short Co');
        $l = self::makeListing($tenant);
        $c = $this->makeCustomer(['email' => 'buyer@example.com']);
        $this->actingAs($c)->post(route('account.shortlist.toggle'), ['key' => 'L:'.$l->id])->assertSessionHas('ok');
        $this->actingAs($c)->get(route('account.shortlist'))->assertOk()->assertSee('Sunny 2 BHK Flat');
        $this->get(route('market.compare', ['keys' => 'L:'.$l->id.',L:999999']))->assertOk()->assertSee('Sunny 2 BHK Flat')->assertSee('Built-up area');

        $this->actingAs($c)->post(route('account.searches.save'), ['type' => ['flat'], 'place' => 'Thanjavur'])->assertSessionHas('ok');
        $s = SavedSearch::where('customer_id', $c->id)->firstOrFail();
        $this->assertSame(['flat'], $s->filters['types']);
        $new = self::makeListing($tenant, ['title' => 'Brand new flat', 'slug' => 'brand-new-flat-1']);
        Mail::fake();
        (new \App\Jobs\MatchRequirements(null, $new->id))->handle();
        Mail::assertQueued(\App\Mail\TemplateMail::class, fn ($m) => $m->hasTo('buyer@example.com'));
        $this->assertDatabaseHas('saved_search_matches', ['saved_search_id' => $s->id, 'item_key' => 'L:'.$new->id]);
        // Only once per item.
        Mail::fake();
        (new \App\Jobs\MatchRequirements(null, $new->id))->handle();
        Mail::assertNothingQueued();

        $this->actingAs($c)->post(route('account.testimonial'), ['body' => 'Found my flat in a week, the owner called back the same day.', 'rating' => 5])->assertSessionHas('ok');
        $this->assertDatabaseHas('testimonials', ['customer_id' => $c->id, 'status' => 'pending']);
        $this->get(route('home'))->assertOk()->assertDontSee('Found my flat in a week');
    }

    public function test_report_listing_goes_to_app_moderation(): void
    {
        [$tenant] = $this->makeTenant('Rep Co');
        $l = self::makeListing($tenant);
        $this->post(route('market.listing.report', $l), ['reason' => 'fake', 'details' => 'Photos are from another site'])->assertSessionHas('ok');
        $this->assertDatabaseHas('listing_reports', ['listing_id' => $l->id, 'reason' => 'fake', 'status' => 'open']);
        $this->actingAs($this->appAdmin())->get(route('app.listings.index', ['tab' => 'reports']))->assertOk()->assertSee('Photos are from another site');
        $this->actingAs($this->appAdmin())->post(route('app.listings.hide', $l), ['reason' => 'Fake photos'])->assertSessionHas('ok');
        $this->get($l->publicUrl())->assertNotFound();
    }

    // ------------------------------------------------------------------ 17.8 AI search

    private function enableAi(): void
    {
        AppSettings::set('ai.enabled', '1');
        AppSettings::set('ai.api_key', 'sk-test');
        AppSettings::set('ai.market_search_enabled', '1');
    }

    public function test_ai_search_returns_only_database_records(): void
    {
        [$tenant] = $this->makeTenant('AI Co');
        $flat = self::makeListing($tenant);
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'tool_use', 'name' => 'set_filters', 'input' => ['types' => ['flat'], 'places' => ['Thanjavur'], 'budget_max' => 5000000, 'bhk_min' => 2]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]])
            ->push(['content' => [['type' => 'tool_use', 'name' => 'rank_results', 'input' => ['results' => [
                ['key' => 'L:999999', 'why' => 'Invented property'], ['key' => 'L:'.$flat->id, 'why' => '2 BHK in Thanjavur within budget'],
            ]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]]),
        ]);
        $this->get(route('market.search', ['q' => '2 bhk flat in thanjavur under 50 lakh']))->assertOk()
            ->assertSee('Sunny 2 BHK Flat')->assertSee('2 BHK in Thanjavur within budget')->assertDontSee('Invented property');
        $log = AiSearchLog::latest('id')->firstOrFail();
        $this->assertTrue($log->used_ai);
        $this->assertSame(1, $log->result_count);
        // Same query within the hour comes from the cache (no new API call).
        Http::fake(['api.anthropic.com/*' => Http::response([], 500)]);
        $this->get(route('market.search', ['q' => '2 bhk flat in thanjavur under 50 lakh']))->assertOk()->assertSee('2 BHK in Thanjavur within budget');
        $this->assertTrue(AiSearchLog::latest('id')->first()->cached);
    }

    public function test_ai_search_falls_back_when_ai_is_off_and_relaxes_filters(): void
    {
        [$tenant] = $this->makeTenant('NoAI Co');
        $flat = self::makeListing($tenant, ['price' => 5400000]);
        AppSettings::set('ai.enabled', '0');
        Http::fake();
        // Budget 50 lakh misses a 54 lakh flat; relaxing by 10 % finds it and says so.
        $this->get(route('market.search', ['q' => '2 bhk flat in Thanjavur under 50 lakh']))->assertOk()
            ->assertSee('Sunny 2 BHK Flat')->assertSee('Budget widened by 10%')->assertSee('keyword search');
        Http::assertNothingSent();
        $this->assertFalse(AiSearchLog::latest('id')->first()->used_ai);
        // Daily cap reached → keyword search too.
        $this->enableAi();
        AppSettings::set('ai.market_search_daily_cap', '1');
        \App\Models\AiUsageLog::create(['feature' => 'market_search_parse', 'model' => 'x', 'credits' => 1, 'status' => 'ok']);
        $this->assertFalse(\App\Services\ClaudeClient::groupAvailable('market_search'));
        $this->get(route('market.search', ['q' => 'flat in Thanjavur']))->assertOk()->assertSee('Sunny 2 BHK Flat');
        Http::assertNothingSent();
    }

    public function test_nearby_districts_relaxation(): void
    {
        [$tenant] = $this->makeTenant('Near Co');
        self::makeListing($tenant, ['city' => 'Trichy', 'district' => 'Tiruchirappalli', 'title' => 'Trichy flat', 'slug' => 'trichy-flat-1']);
        $res = PropertySearch::search(PropertySearch::clean(['types' => ['flat'], 'places' => ['Thanjavur']]), 'relevance', true);
        $this->assertSame(['Trichy flat'], $res['items']->pluck('title')->all());
        $this->assertStringContainsString('nearby districts', implode(' ', $res['relaxed']));
    }

    public function test_home_page_and_nav(): void
    {
        [$tenant] = $this->makeTenant('Home Co');
        self::makeListing($tenant, ['featured' => true, 'title' => 'Featured flat', 'slug' => 'featured-flat-1']);
        $this->get(route('home'))->assertOk()->assertSee('Featured flat')->assertSee('Browse by property type')->assertSee('Post property')->assertSee('Thanjavur');
    }
}
