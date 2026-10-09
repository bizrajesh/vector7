<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Plot;
use App\Services\RequirementSearch;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    private function launched(): array
    {
        [$tenant, $admin] = $this->makeTenant('Market Co '.uniqid());
        $p = LaunchTest::readyProject($tenant);
        $this->actingAs($admin)->post(route('ws.launch.layout', $p), ['layout' => UploadedFile::fake()->image('layout.png', 600, 400)]);
        $rows = [];
        foreach ([['1', 1200, 'East', 1000, 'Available'], ['2', 1500, 'North', 1100, 'Available'], ['3', 2400, 'East', 900, 'Blocked'], ['4', 1300, 'West', 1000, 'Available']] as [$no, $sz, $f, $r, $st]) {
            $rows[] = ['plot_no' => $no, 'patta_number' => '10'.$no, 'size_sqft' => (string) $sz, 'facing' => $f, 'rate_per_sqft' => (string) $r, 'status' => $st,
                'offer' => $no === '1' ? 'Launch offer' : '', 'offer_rate_per_sqft' => $no === '1' ? '900' : '', 'offer_valid_till' => $no === '1' ? today()->addDays(5)->format('d-m-Y') : ''];
        }
        $this->actingAs($admin)->post(route('ws.launch.import.confirm', $p), ['rows_json' => json_encode($rows)]);
        $this->actingAs($admin)->post(route('ws.launch.details', $p), ['promo_text' => 'Quiet layout near the river.', 'facilities_text' => "BT roads\nStreet lights", 'is_featured' => '1']);
        $this->actingAs($admin)->post(route('ws.launch.go-live', $p))->assertSessionHas('ok');
        auth('web')->logout();
        $this->flushSession();

        return [$tenant, $admin, $p->fresh()];
    }

    public function test_home_projects_project_and_plot_pages(): void
    {
        [, , $p] = $this->launched();
        $this->get('/')->assertOk()->assertSee($p->name)->assertSee('application/ld+json', false)->assertSee('"@type":"Organization"', false);
        $this->get(route('market.projects'))->assertOk()->assertSee($p->name);
        $this->get(route('market.projects', ['budget' => 100000]))->assertOk()->assertDontSee($p->name);
        $page = $this->get($p->publicUrl())->assertOk();
        $page->assertSee('<link rel="canonical" href="'.$p->publicUrl().'">', false);
        $page->assertSee('RealEstateListing')->assertSee('BreadcrumbList');
        $page->assertSee('data-plot=', false)->assertSee('OFFER')->assertSee('Save ₹1,20,000');
        $page->assertSee('Launch offer');
        $this->assertSame(1, substr_count($page->getContent(), '<h1'));
        $this->get('/projects/'.$p->slug)->assertRedirect($p->publicUrl())->assertStatus(301);
        $this->get('/projects/wrong-location/'.$p->slug)->assertStatus(301);
        $plot = Plot::withoutGlobalScopes()->where('project_id', $p->id)->where('plot_no', '1')->first();
        $this->get($plot->publicUrl())->assertOk()->assertSee('Plot 1')->assertSee('"@type":"Product"', false)->assertSee('Book this plot');
        $this->get(route('market.layout', $p->slug))->assertOk()->assertHeader('Cache-Control');
        // sold plots leave the showcase and redirect
        $plot->update(['status' => 'sold']);
        $this->get($plot->publicUrl())->assertStatus(301);
    }

    public function test_unlaunched_projects_are_hidden(): void
    {
        [$tenant] = $this->makeTenant('Hidden Co');
        $p = LaunchTest::readyProject($tenant);
        $this->get('/projects/'.$p->slug)->assertNotFound();
        $this->get(route('market.layout', $p->slug))->assertNotFound();
    }

    public function test_sitemap_and_robots(): void
    {
        [, , $p] = $this->launched();
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString($p->publicUrl(), $xml);
        $this->assertStringContainsString('<lastmod>', $xml);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /workspace')->assertSee('Sitemap:');
        config(['seo.indexable' => false]);
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        \App\Services\AppSettings::set('seo.ai_crawlers', 'block');
        config(['seo.indexable' => true]);
        $this->get('/robots.txt')->assertSee('User-agent: GPTBot');
    }

    public function test_requirement_search_local_parser(): void
    {
        [, , $p] = $this->launched();
        $f = RequirementSearch::parseLocally('1,200 sq ft east-facing plot near Vallam under ₹15 lakh');
        $this->assertEquals(1500000, $f['budget_max']);
        $this->assertEquals(1080, $f['size_min']);
        $this->assertEquals(1320, $f['size_max']);
        $this->assertSame('East', $f['facing']);
        $this->assertSame('Vallam', $f['location']);
        $matches = RequirementSearch::matches($f);
        $this->assertSame(['1'], $matches->pluck('plot_no')->all());
        $this->get(route('market.requirement', ['q' => '1200 sqft east facing under 15 lakh in Vallam']))->assertOk()->assertSee('1 matching plot');
        $this->assertEqualsWithDelta(5 * 435.6 * 0.9, RequirementSearch::parseLocally('5 cents north facing')['size_min'], 1);
    }

    public function test_enquiry_goes_to_tenant(): void
    {
        [$tenant, , $p] = $this->launched();
        $this->post(route('market.enquiry'), ['project_id' => $p->id, 'name' => 'Asha', 'email' => 'asha@example.com', 'mobile' => '9876543210', 'message' => 'Call me'])->assertSessionHas('ok');
        $e = Enquiry::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame($tenant->id, $e->tenant_id);
        // honeypot
        $this->post(route('market.enquiry'), ['project_id' => $p->id, 'name' => 'Bot', 'email' => 'b@example.com', 'mobile' => '9876543210', 'message' => 'x', 'website' => 'spam'])->assertSessionHasErrors('website');
    }

    public function test_static_pages_render(): void
    {
        foreach (['market.services', 'market.about', 'market.support', 'market.pricing', 'market.privacy', 'market.terms', 'market.requirement', 'login', 'register', 'signup'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('market.service', 'legal-title-opinion'))->assertOk()->assertSee('Legal title opinion');
        $this->post(route('market.contact'), ['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello'])->assertSessionHas('ok');
    }
}
