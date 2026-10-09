<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Service;
use App\Models\SocialPost;
use App\Models\Ticket;
use App\Services\AppSettings;
use App\Services\SalesService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** App workspace modules: services, enquiries, help desk, marketing, SEO, read-only views, and tenant customers/enquiries/tickets. */
class AppModulesTest extends TestCase
{
    public function test_app_manager_sees_every_module_except_iam_and_settings(): void
    {
        $m = $this->appManager();
        foreach (['app.dashboard', 'app.subscriptions.index', 'app.plans.index', 'app.customers.index', 'app.projects.index', 'app.sales.index',
            'app.services.index', 'app.enquiries.index', 'app.tickets.index', 'app.marketing.index', 'app.masters.index', 'app.seo.index'] as $r) {
            $this->actingAs($m)->get(route($r))->assertOk();
        }
        $this->actingAs($m)->get(route('app.iam.index'))->assertForbidden();
        $this->actingAs($m)->get(route('app.settings.index'))->assertForbidden();
        $this->actingAs($m)->get(route('app.audit.index'))->assertForbidden();
    }

    public function test_tenant_users_cannot_open_app_workspace(): void
    {
        [$tenant, $admin] = $this->makeTenant('T Co');
        $this->actingAs($admin)->get(route('app.services.index'))->assertForbidden();
        $this->actingAs($this->makeUser($tenant, 'support'))->get(route('app.tickets.index'))->assertForbidden();
    }

    public function test_service_crud_and_marketplace_listing(): void
    {
        $admin = $this->appAdmin();
        $this->actingAs($admin)->post(route('app.services.store'), ['name' => 'Patta transfer help', 'summary' => 'We handle the paperwork', 'available_to' => 'both', 'price' => 4999, 'is_active' => 1])->assertSessionHasNoErrors();
        $s = Service::where('name', 'Patta transfer help')->firstOrFail();
        $this->assertSame('patta-transfer-help', $s->slug);
        $this->get(route('market.services'))->assertOk()->assertSee('Patta transfer help');
        $this->actingAs($admin)->put(route('app.services.update', $s), ['name' => 'Patta transfer help', 'available_to' => 'customer', 'price_on_request' => 1, 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertNull($s->fresh()->price);
        $this->assertFalse($s->fresh()->is_active);
        $this->actingAs($admin)->delete(route('app.services.destroy', $s))->assertSessionHasNoErrors();
        $this->assertNull(Service::find($s->id));
    }

    public function test_ticket_round_trip_between_tenant_and_help_desk(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant('Ticket Co');
        $this->actingAs($admin)->post(route('ws.tickets.store'), ['category' => 'technical', 'priority' => 'urgent', 'subject' => 'Map broken', 'body' => 'Plot map is blank'])->assertRedirect();
        $t = Ticket::withoutGlobalScopes()->where('subject', 'Map broken')->firstOrFail();
        $this->assertSame($tenant->id, $t->tenant_id);
        $this->assertEqualsWithDelta(8 * 3600, $t->created_at->diffInSeconds($t->sla_due_at), 5);

        $agent = $this->appAdmin();
        $this->actingAs($agent)->post(route('app.tickets.reply', $t), ['body' => 'Internal: check CSP', 'is_internal' => 1])->assertSessionHasNoErrors();
        $this->actingAs($agent)->post(route('app.tickets.reply', $t), ['body' => 'Fixed — please refresh.', 'set_status' => 'resolved'])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame('resolved', $t->status);
        $this->assertSame($agent->id, $t->assignee_id);
        $this->assertNotNull($t->resolved_at);

        // The tenant sees the public reply but never the internal note.
        $this->actingAs($admin)->get(route('ws.tickets.show', $t))->assertOk()->assertSee('Fixed — please refresh.')->assertDontSee('Internal: check CSP');

        // Another tenant cannot open it.
        [, $other] = $this->makeTenant('Other Co');
        $this->actingAs($other)->get(route('ws.tickets.show', $t))->assertNotFound();
    }

    public function test_enquiry_assignment_from_app_to_tenant_and_conversion(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant('Enq Co');
        $e = Enquiry::create(['tenant_id' => null, 'assigned_team' => 'app', 'name' => 'Hari', 'email' => 'hari@example.com', 'message' => 'Need a plot', 'status' => 'new']);
        $this->actingAs($this->appAdmin())->put(route('app.enquiries.update', $e), ['assigned_team' => 'tenant', 'tenant_id' => $tenant->id, 'status' => 'new'])->assertSessionHasNoErrors();
        $this->assertSame($tenant->id, $e->fresh()->tenant_id);

        $this->actingAs($admin)->get(route('ws.enquiries.index'))->assertOk()->assertSee('Hari');
        $this->actingAs($admin)->put(route('ws.enquiries.update', $e), ['status' => 'converted'])->assertSessionHasErrors('booking_no');
        $this->actingAs($admin)->put(route('ws.enquiries.update', $e), ['status' => 'contacted', 'follow_up_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('contacted', $e->fresh()->status);

        [, $other] = $this->makeTenant('Enq Other');
        $this->actingAs($other)->put(route('ws.enquiries.update', $e), ['status' => 'closed'])->assertNotFound();
    }

    public function test_tenant_sees_only_associated_customers(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant('Cust Co '.uniqid());
        $p = LaunchTest::readyProject($tenant);
        $p->update(['status' => 'launched', 'launched_at' => now()]);
        $plot = app(Tenancy::class)->run($tenant, fn () => \App\Models\Plot::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'plot_no' => '1', 'patta_number' => '1', 'size_sqft' => 1000, 'facing' => 'East', 'rate_per_sqft' => 1000]));
        $mine = $this->makeCustomer(['name' => 'Mine Buyer']);
        $stranger = $this->makeCustomer(['name' => 'Stranger Buyer']);
        SalesService::book($plot, $mine, 'marketplace', null, '1.1.1.1');

        $this->actingAs($admin)->get(route('ws.customers.index'))->assertOk()->assertSee('Mine Buyer')->assertDontSee('Stranger Buyer');
        $this->actingAs($admin)->get(route('ws.customers.show', $mine->id))->assertOk();
        $this->actingAs($admin)->get(route('ws.customers.show', $stranger->id))->assertNotFound();
        // Accounts role has no customer permission.
        $this->actingAs($this->makeUser($tenant, 'tenant_account'))->get(route('ws.customers.index'))->assertForbidden();
    }

    public function test_marketing_post_without_connected_account_stays_draft(): void
    {
        [$tenant] = $this->makeTenant('Mk Co');
        $p = LaunchTest::readyProject($tenant);
        $p->update(['status' => 'launched', 'launched_at' => now()]);
        $this->actingAs($this->appAdmin())->post(route('app.marketing.generate'), ['project_id' => $p->id, 'platform' => 'instagram', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')])->assertSessionHasNoErrors();
        $post = SocialPost::withoutGlobalScopes()->whereNull('tenant_id')->firstOrFail();
        $this->assertSame('draft', $post->status);
        $this->assertSame('Not connected', $post->error);
        $this->assertStringContainsString($p->name, $post->caption);
        $this->actingAs($this->appAdmin())->get(route('app.marketing.index'))->assertOk()->assertSee('Not connected');
    }

    public function test_seo_settings_write_ai_crawler_rule_into_robots(): void
    {
        $this->actingAs($this->appAdmin())->put(route('app.seo.update'), ['default_title' => 'vector7 plots', 'default_description' => 'Approved plots', 'ai_crawlers' => 'block'])->assertSessionHasNoErrors();
        $this->assertSame('block', AppSettings::get('seo.ai_crawlers'));
        $this->get('/robots.txt')->assertOk()->assertSee('User-agent: GPTBot')->assertSee('Sitemap:');
        $this->actingAs($this->appAdmin())->post(route('app.seo.build'))->assertSessionHasNoErrors();
    }

    public function test_app_read_only_views_cover_all_tenants(): void
    {
        [$a] = $this->makeTenant('Alpha Promoters');
        [$b] = $this->makeTenant('Beta Promoters');
        $pa = LaunchTest::readyProject($a);
        $pb = LaunchTest::readyProject($b);
        $m = $this->appManager();
        $this->actingAs($m)->get(route('app.projects.index'))->assertOk()->assertSee($pa->name)->assertSee($pb->name);
        $this->actingAs($m)->get(route('app.projects.index', ['tenant' => $a->id]))->assertOk()->assertSee($pa->name)->assertDontSee($pb->name);
        $this->actingAs($m)->get(route('app.projects.show', $pb))->assertOk()->assertSee('Beta Promoters');
        $this->actingAs($m)->get(route('app.sales.index', ['tab' => 'bookings']))->assertOk();
        $this->actingAs($m)->get(route('app.projects.index', ['export' => 'xlsx']))->assertOk()->assertHeader('content-disposition');
    }
}
