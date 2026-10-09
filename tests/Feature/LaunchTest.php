<?php

namespace Tests\Feature;

use App\Models\Plot;
use App\Models\Project;
use App\Services\DxfParser;
use App\Services\IdGenerator;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LaunchTest extends TestCase
{
    public static function readyProject($tenant): Project
    {
        return app(Tenancy::class)->run($tenant, fn () => Project::create([
            'tenant_id' => $tenant->id, 'project_code' => IdGenerator::next($tenant->id, 'project').rand(10, 99), 'name' => 'Lotus Garden '.rand(1, 9999), 'approval_type' => 'Village',
            'location' => 'Vallam', 'district' => 'Thanjavur', 'size_acres' => 2, 'status' => 'ready_to_launch',
        ]))->fresh();
    }

    private function csv(array $rows): string
    {
        $head = 'plot_no,patta_number,size_sqft,length_ft,width_ft,facing,east_boundary,west_boundary,north_boundary,south_boundary,road_width_ft,corner_plot,rate_per_sqft,offer,offer_rate_per_sqft,offer_valid_till,status';

        return $head."\n".implode("\n", $rows);
    }

    public function test_import_validation_errors_import_nothing(): void
    {
        [$tenant, $admin] = $this->makeTenant('L Co');
        $p = self::readyProject($tenant);
        $future = today()->addDays(10)->format('d-m-Y');
        $past = today()->subDay()->format('d-m-Y');
        $csv = $this->csv([
            "1,1234,1200,40,30,East,,,,,30,Y,1000,,,,Available",
            "1,1235,1200,40,30,East,,,,,30,N,1000,,,,Available",               // duplicate plot_no
            "2,,1200,40,30,East,,,,,30,N,1000,,,,Available",                   // missing patta
            "3,77,1200,40,30,Eastish,,,,,30,N,1000,,,,Sold",                   // bad facing, bad status
            "4,78,1200,40,30,East,,,,,30,N,1000,Promo,1100,$future,Available", // offer not lower
            "5,79,1200,40,30,East,,,,,30,N,1000,Promo,900,,Available",         // offer without date
            "6,80,1200,40,30,East,,,,,30,N,1000,Promo,900,$past,Available",    // date in past
            "7,81,0,40,30,East,,,,,30,N,,,,,Available",                        // missing size / rate
        ]);
        $r = $this->actingAs($admin)->post(route('ws.launch.import', $p), ['csv' => $csv])->assertOk();
        $errs = $r->viewData('rowErrors');
        $all = json_encode($errs);
        $this->assertStringContainsString('duplicate plot_no', $all);
        $this->assertStringContainsString('patta_number is required', $all);
        $this->assertStringContainsString('facing must be', $all);
        $this->assertStringContainsString('status must be Available or Blocked', $all);
        $this->assertStringContainsString('offer price must be lower', $all);
        $this->assertStringContainsString('offer_valid_till (DD-MM-YYYY) is required', $all);
        $this->assertStringContainsString('in the past', $all);
        $this->assertStringContainsString('size_sqft must be', $all);
        $this->assertStringContainsString('rate_per_sqft must be', $all);
        $this->assertSame(0, Plot::withoutGlobalScopes()->where('project_id', $p->id)->count());

        // confirm with an invalid grid still imports nothing
        $rows = [['plot_no' => '1', 'patta_number' => '', 'size_sqft' => '1200', 'facing' => 'East', 'rate_per_sqft' => '1000', 'status' => 'Available']];
        $this->actingAs($admin)->post(route('ws.launch.import.confirm', $p), ['rows_json' => json_encode($rows)])->assertOk()->assertSee('Nothing has been imported');
        $this->assertSame(0, Plot::withoutGlobalScopes()->where('project_id', $p->id)->count());
    }

    public function test_valid_import_then_reimport_updates_by_plot_no(): void
    {
        [$tenant, $admin] = $this->makeTenant('L2 Co');
        $p = self::readyProject($tenant);
        $future = today()->addDays(10)->format('d-m-Y');
        $rows = [
            ['plot_no' => '1', 'patta_number' => '1234', 'size_sqft' => '1200', 'facing' => 'East', 'corner_plot' => 'Y', 'rate_per_sqft' => '1000', 'offer' => 'Diwali', 'offer_rate_per_sqft' => '900', 'offer_valid_till' => $future, 'status' => 'Available'],
            ['plot_no' => '2', 'patta_number' => '1234/2', 'size_sqft' => '1500', 'facing' => 'north', 'rate_per_sqft' => '1000', 'status' => 'Blocked'],
        ];
        $this->actingAs($admin)->post(route('ws.launch.import.confirm', $p), ['rows_json' => json_encode($rows)])->assertRedirect(route('ws.launch.show', $p));
        $plots = Plot::withoutGlobalScopes()->where('project_id', $p->id)->orderBy('plot_no')->get();
        $this->assertCount(2, $plots);
        $this->assertEquals(1200000, $plots[0]->actualPrice());
        $this->assertEquals(1080000, $plots[0]->offerPrice());
        $this->assertTrue($plots[0]->offerIsActive());
        $this->assertEquals(1080000, $plots[0]->currentPrice());
        $this->assertSame('North', $plots[1]->facing);
        $this->assertSame('blocked', $plots[1]->status);
        // re-import updates plot 2 rate and status
        $rows2 = [['plot_no' => '2', 'patta_number' => '1234/2', 'size_sqft' => '1500', 'facing' => 'North', 'rate_per_sqft' => '1100', 'status' => 'Available']];
        $this->actingAs($admin)->post(route('ws.launch.import.confirm', $p), ['rows_json' => json_encode($rows2)])->assertSessionHas('ok');
        $p2 = Plot::withoutGlobalScopes()->where('project_id', $p->id)->where('plot_no', '2')->first();
        $this->assertEquals(1100, (float) $p2->rate_per_sqft);
        $this->assertSame('available', $p2->status);
        $this->assertSame(2, Plot::withoutGlobalScopes()->where('project_id', $p->id)->count());
        // sellable % recalculated from actual plots
        $this->assertEquals(round(2700 / (2 * 43560) * 100, 2), (float) $p->fresh()->sellable_pct);
    }

    public function test_offer_used_only_while_valid(): void
    {
        [$tenant] = $this->makeTenant('Offer Co');
        $p = self::readyProject($tenant);
        $plot = app(Tenancy::class)->run($tenant, fn () => Plot::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'plot_no' => '9', 'patta_number' => '1', 'size_sqft' => 1000, 'facing' => 'East',
            'rate_per_sqft' => 1000, 'offer_text' => 'Promo', 'offer_rate_per_sqft' => 800, 'offer_valid_till' => today()->addDays(2)]));
        $this->assertEquals(800000, $plot->priceOn(today()));
        $this->assertEquals(800000, $plot->priceOn(today()->addDays(2)));
        $this->assertEquals(1000000, $plot->priceOn(today()->addDays(3)));
        $this->travel(3)->days();
        $this->assertFalse($plot->fresh()->offerIsActive());
        $this->assertEquals(1000000, $plot->fresh()->currentPrice());
    }

    public function test_sales_and_support_are_read_only_on_launch(): void
    {
        [$tenant, $admin] = $this->makeTenant('RO Co');
        $p = self::readyProject($tenant);
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $support = $this->makeUser($tenant, 'support');
        $plot = app(Tenancy::class)->run($tenant, fn () => Plot::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'plot_no' => '1', 'patta_number' => '1', 'size_sqft' => 1000, 'facing' => 'East', 'rate_per_sqft' => 1000]));
        foreach ([$sales, $support] as $u) {
            $page = $this->actingAs($u)->get(route('ws.launch.show', $p))->assertOk();
            $page->assertDontSee('and its plots on the marketplace')->assertDontSee('Load plots')->assertDontSee('>Edit<', false)->assertDontSee('Upload layout picture');
            $this->actingAs($u)->post(route('ws.launch.go-live', $p))->assertForbidden();
            $this->actingAs($u)->post(route('ws.launch.import', $p), ['csv' => 'x'])->assertForbidden();
            $this->actingAs($u)->post(route('ws.launch.import.confirm', $p), ['rows_json' => '[]'])->assertForbidden();
            $this->actingAs($u)->put(route('ws.plots.update', [$p, $plot]), ['rate_per_sqft' => 1])->assertForbidden();
            $this->actingAs($u)->delete(route('ws.plots.destroy', [$p, $plot]))->assertForbidden();
            $this->actingAs($u)->post(route('ws.launch.layout', $p))->assertForbidden();
            $this->actingAs($u)->post(route('ws.launch.details', $p), ['promo_text' => 'x'])->assertForbidden();
        }
        $this->actingAs($admin)->get(route('ws.launch.show', $p))->assertOk()->assertSee('and its plots on the marketplace');
    }

    public function test_go_live_requires_layout_and_plots(): void
    {
        [$tenant, $admin] = $this->makeTenant('Live Co');
        $p = self::readyProject($tenant);
        $this->actingAs($admin)->post(route('ws.launch.go-live', $p))->assertSessionHas('error');
        $png = UploadedFile::fake()->image('layout.png', 800, 600);
        $this->actingAs($admin)->post(route('ws.launch.layout', $p), ['layout' => $png])->assertSessionHas('ok');
        $this->actingAs($admin)->post(route('ws.launch.import.confirm', $p), ['rows_json' => json_encode([['plot_no' => '1', 'patta_number' => '5', 'size_sqft' => '1000', 'facing' => 'East', 'rate_per_sqft' => '900', 'status' => 'Available']])]);
        $this->actingAs($admin)->post(route('ws.launch.go-live', $p))->assertSessionHas('ok');
        $this->assertSame('launched', $p->fresh()->status);
        $this->assertSame(800, $p->fresh()->layout_width);
    }

    public function test_dxf_parser_measures_closed_polylines_and_reads_labels(): void
    {
        $dxf = "0\nSECTION\n2\nHEADER\n9\n\$INSUNITS\n70\n2\n0\nENDSEC\n0\nSECTION\n2\nENTITIES\n"
            ."0\nLWPOLYLINE\n90\n4\n70\n1\n10\n0\n20\n0\n10\n40\n20\n0\n10\n40\n20\n30\n10\n0\n20\n30\n"
            ."0\nLWPOLYLINE\n90\n4\n70\n1\n10\n40\n20\n0\n10\n90\n20\n0\n10\n90\n20\n30\n10\n40\n20\n30\n"
            ."0\nTEXT\n10\n20\n20\n15\n1\nPlot 1\n"
            ."0\nTEXT\n10\n60\n20\n15\n1\n2\n"
            ."0\nENDSEC\n0\nEOF\n";
        $plots = DxfParser::plots($dxf);
        $this->assertCount(2, $plots);
        $this->assertSame('1', $plots[0]['plot_no']);
        $this->assertEquals(1200, $plots[0]['size_sqft']);
        $this->assertEquals(40, $plots[0]['length_ft']);
        $this->assertEquals(30, $plots[0]['width_ft']);
        $this->assertSame('2', $plots[1]['plot_no']);
        $this->assertEquals(1500, $plots[1]['size_sqft']);
        $this->assertCount(4, $plots[0]['map_polygon']);
    }

    public function test_dxf_upload_extracts_editable_rows(): void
    {
        [$tenant, $admin] = $this->makeTenant('Dxf Co');
        $p = self::readyProject($tenant);
        $dxf = "0\nSECTION\n2\nENTITIES\n0\nLWPOLYLINE\n90\n4\n70\n1\n10\n0\n20\n0\n10\n40\n20\n0\n10\n40\n20\n30\n10\n0\n20\n30\n0\nTEXT\n10\n20\n20\n15\n1\n7\n0\nENDSEC\n0\nEOF\n";
        $file = UploadedFile::fake()->createWithContent('layout.dxf', $dxf);
        $r = $this->actingAs($admin)->post(route('ws.launch.extract', $p), ['source' => $file])->assertOk();
        $rows = $r->viewData('rows');
        $this->assertSame('7', $rows[0]['plot_no']);
        $this->assertSame('1200', $rows[0]['size_sqft']);
        $this->assertNotEmpty($r->viewData('rowErrors')); // patta, facing and rate still to be filled
    }

    public function test_sample_csv_and_screens(): void
    {
        [$tenant, $admin] = $this->makeTenant('Screens Co');
        $p = self::readyProject($tenant);
        $this->actingAs($admin)->get(route('ws.launch.sample'))->assertOk()->assertSee('plot_no,patta_number,size_sqft');
        $this->actingAs($admin)->get(route('ws.launch.index'))->assertOk();
        $this->actingAs($admin)->post(route('ws.launch.import', $p), ['csv' => "plot_no,patta_number,size_sqft,facing,rate_per_sqft,status\n1,22,1000,East,1000,Available"])->assertOk()->assertSee('All 1 rows are valid');
        $this->actingAs($admin)->post(route('ws.launch.import', $p), ['csv' => "plot,patta\n1,2"])->assertSessionHas('error');
    }
}
