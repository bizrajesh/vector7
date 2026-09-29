<?php

namespace Tests\Feature;

use App\Models\Layout;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** OWASP A01: a user of tenant A can never reach tenant B's records. */
class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_tenant_layout_returns_404(): void
    {
        $adminA = $this->makeTenantAdmin('a@example.test');
        $adminB = $this->makeTenantAdmin('b@example.test');

        $layoutB = app(TenantContext::class)->run($adminB->tenant, function () {
            $layout = new Layout(['code' => 'B-1', 'name' => 'B layout', 'total_sqft' => 1000, 'sellable_pct' => 55, 'std_plot_sqft' => 1200, 'land_cost' => 0, 'contingency_pct' => 0]);
            $layout->status = 'draft';
            $layout->save();

            return $layout;
        });

        $this->actingAs($adminA)->get("/app/layouts/{$layoutB->id}")->assertNotFound();
        $this->actingAs($adminB)->get("/app/layouts/{$layoutB->id}")->assertOk();
    }

    public function test_queries_without_tenant_context_return_nothing(): void
    {
        $this->makeTenantAdmin('c@example.test');
        app(TenantContext::class)->set(null);

        $this->assertSame(0, Layout::query()->count());
    }
}
