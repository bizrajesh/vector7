<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Registration;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Seeds the full demo tenant and opens every screen as each role, so a broken view or query
 * anywhere in the app fails the build (no 500s; forbidden screens return 403, never 500).
 */
class DemoSmokeTest extends TestCase
{
    private function getRoutes(string $prefix): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()) && str_starts_with((string) $r->getName(), $prefix) && ! str_contains($r->uri(), '{'))
            ->map(fn ($r) => $r->getName())->values()->all();
    }

    public function test_every_screen_renders_with_demo_data(): void
    {
        $this->seed(DemoSeeder::class);
        $users = User::withoutGlobalScopes()->where('email', 'like', '%@demo.vector7.in')->get()->keyBy(fn ($u) => strtok($u->email, '@'));
        $this->assertCount(5, $users);

        foreach ($users as $key => $u) {
            foreach ($this->getRoutes('ws.') as $name) {
                $status = $this->actingAs($u)->get(route($name))->baseResponse->getStatusCode();
                $this->assertContains($status, [200, 302, 403], "$key → $name returned $status");
            }
        }

        $admin = $users['admin'];
        $project = Project::withoutGlobalScopes()->where('status', 'launched')->first();
        $tracking = Project::withoutGlobalScopes()->where('status', 'in_progress')->first();
        $sale = Sale::withoutGlobalScopes()->where('status', 'sale_init')->first();
        $reg = Registration::withoutGlobalScopes()->first();
        foreach ([route('ws.projects.show', $project), route('ws.projects.show', $tracking), route('ws.launch.show', $project), route('ws.sales.show', $sale),
            route('ws.registrations.show', $reg)] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        foreach (['project-progress', 'plot-inventory', 'sales-collections', 'dues'] as $report) {
            $this->actingAs($admin)->get(route('ws.reports.show', $report))->assertOk();
        }

        $appAdmin = $this->appAdmin();
        foreach ($this->getRoutes('app.') as $name) {
            $this->actingAs($appAdmin)->get(route($name))->assertOk();
        }
        $this->actingAs($appAdmin)->get(route('app.customers.show', Customer::first()))->assertOk();
        $this->actingAs($appAdmin)->get(route('app.subscriptions.show', $admin->tenant_id))->assertOk();

        // Marketplace
        foreach (['home', 'market.projects', 'market.services', 'market.about', 'market.support', 'market.pricing', 'sitemap', 'robots'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->get($project->publicUrl())->assertOk()->assertSee('Vallam Green Meadows');
        $plot = Plot::withoutGlobalScopes()->where('project_id', $project->id)->where('status', 'available')->first();
        $plot->setRelation('project', $project);
        $this->get($plot->publicUrl())->assertOk();

        $buyer = Customer::where('email', 'buyer1@demo.vector7.in')->first();
        foreach ($this->getRoutes('account.') as $name) {
            $this->actingAs($buyer, 'customer')->get(route($name))->assertOk();
        }
    }
}
