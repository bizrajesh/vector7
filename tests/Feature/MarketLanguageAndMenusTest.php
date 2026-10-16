<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Marketplace in English / Tamil with a switch, English-only workspaces, and menu-specific filters. */
class MarketLanguageAndMenusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    public function test_marketplace_switches_to_tamil_and_remembers_it(): void
    {
        [$tenant] = $this->makeTenant('Lang Co');
        $l = ListingsMarketplaceTest::makeListing($tenant);
        $this->get('/')->assertOk()->assertSee('lang="en-IN"', false)->assertSee('Browse by property type')->assertSee('தமிழ்');
        $this->get('/?lang=ta')->assertOk()->assertSee('lang="ta-IN"', false)->assertSee('சொத்து வகை வாரியாகப் பாருங்கள்')->assertDontSee('Browse by property type')
            ->assertSee('hreflang="en-IN"', false);
        // Remembered for the next pages (session), including listing pages, filters and the contact block.
        $this->get($l->publicUrl())->assertOk()->assertSee('உரிமையாளரைத் தொடர்புகொள்ளுங்கள்')->assertSee('சதுர அடி');
        $this->get('/rent')->assertOk()->assertSee('மாத வாடகை');
        // Tamil validation messages on the marketplace.
        $this->post(route('market.contact'), ['name' => '', 'email' => 'x', 'message' => ''])->assertSessionHasErrors(['name' => 'பெயர் தேவை.']);
        // Back to English.
        $this->get('/?lang=en')->assertOk()->assertSee('Browse by property type');
    }

    public function test_workspaces_and_staff_logins_stay_english(): void
    {
        [, $admin] = $this->makeTenant('Eng Co');
        $this->get('/?lang=ta')->assertOk();
        $this->withSession(['locale' => 'ta'])->post('/workspace/login', ['email' => 'nobody@example.com', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->get('/workspace/login')->assertOk()->assertSee('Sign in to your workspace')->assertSee('lang="en"', false);
        $this->get('/app/login')->assertOk()->assertSee('vector7 App sign in');
        $this->actingAs($admin)->withSession(['locale' => 'ta'])->get(route('ws.listings.index'))->assertOk()->assertSee('Property listings')->assertDontSee('சொத்து');
        $this->actingAs($this->appAdmin())->withSession(['locale' => 'ta'])->get(route('app.dashboard'))->assertOk()->assertDontSee('தமிழ்நாட்டில்');
    }

    public function test_marketplace_never_shows_the_app_admin_login(): void
    {
        foreach (['/', '/buy', '/support', '/for-promoters', '/login'] as $u) {
            $this->get($u)->assertOk()->assertDontSee('/app/login')->assertDontSee('App admin');
        }
        // An App user browsing the marketplace is not offered the App workspace from it.
        $this->actingAs($this->appAdmin())->get('/')->assertOk()->assertDontSee(route('app.dashboard'));
    }

    public function test_filters_follow_the_menu(): void
    {
        [$tenant] = $this->makeTenant('Menu Co');
        ListingsMarketplaceTest::makeListing($tenant);
        // Buy: all types as pills, sale budgets in lakhs, approval filter.
        $this->get('/buy')->assertOk()->assertSee('Buy a property')->assertSee('Farm land')->assertSee('Approved / RERA only')->assertSee('₹50 lakh');
        // Buy → flats: BHK and furnishing.
        $this->get('/buy/flats')->assertOk()->assertSee('Furnishing')->assertSee('BHK')->assertDontSee('Water source');
        // Buy → farm land: extent in acres and water source.
        $this->get('/buy/farm-land')->assertOk()->assertSee('Water source')->assertSee('acres')->assertDontSee('Furnishing');
        // Rent: only rentable types, monthly rent, no approval / offer filters.
        $this->get('/rent')->assertOk()->assertSee('Rent a property')->assertSee('Monthly rent')->assertSee('Shops &amp; offices', false)
            ->assertDontSee('Approved / RERA only');
        // Layouts: plot size, facing, approving authority, corner plots.
        $this->get('/buy/layouts')->assertOk()->assertSee('New approved layouts')->assertSee('Approved by')->assertSee('Corner plots')->assertSee('Plot size');
        // Filters submit back to the same menu page.
        $this->get('/buy/flats?furnishing=semi')->assertOk()->assertSee('Semi-furnished')->assertDontSee('Sunny 2 BHK Flat');
        $this->get('/buy/flats?bhk=2')->assertOk()->assertSee('Sunny 2 BHK Flat');
        // Services: who it is for; Support: topics; For promoters: what you want to do.
        Service::create(['name' => 'Promoter only service', 'slug' => 'promo-only', 'summary' => 'x', 'available_to' => 'tenant', 'is_active' => true]);
        $this->get('/services?for=buyers')->assertOk()->assertDontSee('Promoter only service');
        $this->get('/services?for=promoters')->assertOk()->assertSee('Promoter only service');
        $this->get('/support?topic=booking')->assertOk()->assertSee('How do I book a plot?')->assertDontSee('A listing looks wrong or fake');
        $this->post(route('market.contact'), ['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hi', 'topic' => 'booking'])->assertSessionHas('ok');
        $this->assertDatabaseHas('enquiries', ['message' => '[Booking & payments] Hi']);
        $this->get('/for-promoters?for=listings')->assertOk()->assertSee('Reach serious buyers and tenants in Tamil Nadu')->assertSee('live property listings');
        // Header menu: Buy, Rent, Layouts, Services, For promoters, Support.
        $this->get('/')->assertSeeInOrder(['Buy', 'Rent', 'Layouts', 'Services', 'For promoters', 'Support']);
    }

    public function test_tamil_search_works_without_ai(): void
    {
        [$tenant] = $this->makeTenant('Ta Search Co');
        ListingsMarketplaceTest::makeListing($tenant);
        $f = \App\Services\PropertySearch::parseLocally('தஞ்சாவூரில் 50 லட்சத்திற்குள் 2 BHK அடுக்குமாடி வீடு');
        $this->assertSame(['flat'], $f['types']);
        $this->assertSame(['Thanjavur'], $f['places']);
        $this->assertEquals(5000000, $f['budget_max']);
        $this->assertSame(2, $f['bhk_min']);
        \App\Services\AppSettings::set('ai.enabled', '0');
        $this->get('/search?lang=ta&q='.urlencode('தஞ்சாவூரில் 50 லட்சத்திற்குள் 2 BHK அடுக்குமாடி வீடு'))->assertOk()->assertSee('Sunny 2 BHK Flat')->assertSee('முக்கியச்சொல் தேடல்');
    }
}
