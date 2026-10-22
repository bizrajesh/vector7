<?php

namespace Tests\Feature;

use App\Models\Plot;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Production: marketplace plot statuses, plot onboarding from the template, and the test-data clean-up script. */
class ProductionReadinessTest extends TestCase
{
    public function test_marketplace_shows_only_available_booked_sold_or_blocked(): void
    {
        foreach (Plot::STATUSES as $key => $label) {
            $p = new Plot(['status' => $key, 'size_sqft' => 1000, 'rate_per_sqft' => 900, 'plot_no' => '1']);
            $expected = in_array($key, ['available', 'booked', 'sold'], true) ? $key : 'blocked';
            $this->assertSame($expected, $p->marketStatus(), $key);
            $this->assertSame(Plot::MARKET_STATUSES[$expected], $p->tooltipData()['status']);
            $this->assertSame($expected, $p->tooltipData()['statusKey']);
            $this->assertSame($label, $p->tooltipData(true)['status'], 'workspace keeps the real status');
        }

        [$tenant] = $this->makeTenant('Status Co');
        $project = LaunchTest::readyProject($tenant);
        app(Tenancy::class)->run($tenant, function () use ($project, $tenant) {
            $project->update(['status' => 'launched', 'launched_at' => now(), 'slug' => 'status-garden']);
            foreach (['available' => 'A1', 'booked' => 'B1', 'sale_init' => 'S1', 'ror' => 'R1', 'sold' => 'D1', 'blocked' => 'K1'] as $st => $no) {
                Plot::create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'plot_no' => $no, 'patta_number' => 'P'.$no, 'size_sqft' => 1200, 'facing' => 'East', 'rate_per_sqft' => 900, 'status' => $st]);
            }
        });
        $html = $this->get($project->fresh()->publicUrl())->assertOk()->getContent();
        foreach (['Sale Init', 'ROR'] as $hidden) {
            $this->assertStringNotContainsString('>'.$hidden.'<', $html, $hidden);
            $this->assertStringNotContainsString('"status":"'.$hidden.'"', $html, $hidden);
        }
        $this->assertStringContainsString('data-status="blocked"', $html);
        $this->assertStringContainsString('data-status="booked"', $html);
        $this->assertSame(3, substr_count($html, 'data-status="blocked"'), 'Sale Init, ROR and Blocked show as Blocked');
        $this->assertSame(1, substr_count($html, 'data-status="sold"'));
        $sold = Plot::withoutGlobalScopes()->where('plot_no', 'D1')->first()->setRelation('project', $project->fresh());
        $this->get($sold->publicUrl())->assertOk()->assertSee('This plot is Sold')->assertDontSee('Book this plot');
    }

    public function test_plot_onboarding_page_template_and_upload(): void
    {
        [$tenant, $admin] = $this->makeTenant('Onboard Co');
        $p = LaunchTest::readyProject($tenant);
        $this->actingAs($admin)->get(route('ws.plot-onboarding.index'))->assertOk()->assertSee('Plot onboarding')->assertSee($p->name);
        $this->actingAs($admin)->get(route('ws.plot-onboarding.template'))->assertOk()->assertDownload('vector7-plot-onboarding-template.xlsx');
        $this->actingAs($admin)->get(route('ws.dashboard'))->assertSee(route('ws.plot-onboarding.index'), false);

        // The shipped template itself imports (its 3 sample rows are valid).
        $tpl = new UploadedFile(resource_path('templates/vector7-plot-onboarding-template.xlsx'), 't.xlsx', null, null, true);
        $r = $this->actingAs($admin)->post(route('ws.plot-onboarding.upload'), ['project_id' => $p->id, 'file' => $tpl])->assertOk();
        $this->assertSame([], $r->viewData('rowErrors'));
        $this->assertCount(3, $r->viewData('rows'));

        // A workbook with a notes sheet first, plot_status instead of status and size_cents instead of size_sqft.
        $file = new UploadedFile(base_path('tests/fixtures/plot-onboard-with-helpers.xlsx'), 'plots.xlsx', null, null, true);
        $r = $this->actingAs($admin)->post(route('ws.plot-onboarding.upload'), ['project_id' => $p->id, 'file' => $file])->assertOk();
        $this->assertSame([], $r->viewData('rowErrors'));
        $rows = collect($r->viewData('rows'))->keyBy('plot_no');
        $this->assertSame('Blocked', $rows['1']['status']);        // Reserved → Blocked
        $this->assertSame('Available', $rows['2']['status']);
        $this->assertSame('Available', $rows['3']['status']);      // explicit status wins over plot_status
        $this->assertEquals(round(3.9 * 435.6, 2), (float) $rows['2']['size_sqft']);

        // Another promoter's project cannot be targeted.
        [$other] = $this->makeTenant('Other Co');
        $foreign = LaunchTest::readyProject($other);
        $this->actingAs($admin)->post(route('ws.plot-onboarding.upload'), ['project_id' => $foreign->id, 'file' => $file])->assertNotFound();
    }

    public function test_cleanup_script_lists_every_table_and_keeps_admin_and_prerequisites(): void
    {
        $sql = file_get_contents(database_path('sql/production-cleanup.sql'));
        preg_match_all('/(?:TRUNCATE TABLE|DELETE FROM|UPDATE)\s+([a-z_]+)/', $sql, $m);
        preg_match('/-- ---------- 10\. Kept as they are.*?\n((?:--.*\n)+)/s', $sql, $kept);
        $listed = array_unique(array_merge($m[1], preg_split('/[\s,\-]+/', $kept[1] ?? '', -1, PREG_SPLIT_NO_EMPTY)));
        $tables = array_map(fn ($t) => $t['name'], Schema::getTables());
        $this->assertSame([], array_values(array_diff($tables, $listed)), 'New tables must be added to database/sql/production-cleanup.sql');
        foreach (['migrations', 'permissions', 'plans', 'plan_limits', 'topup_packs', 'services', 'sros', 'email_templates', 'settings', 'stage_masters', 'subtask_masters', 'facility_masters', 'document_checklists', 'feature_masters'] as $keep) {
            $this->assertDoesNotMatchRegularExpression('/TRUNCATE TABLE '.$keep.';/', $sql, "$keep is a prerequisite");
        }
        $this->assertStringContainsString("base_role = 'app_admin'", $sql);
        $this->assertStringNotContainsString('DROP TABLE', strtoupper($sql));
    }
}
