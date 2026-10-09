<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Services\ProjectService;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    private function newProject($admin, array $extra = [])
    {
        $this->actingAs($admin)->post(route('ws.projects.store'), array_merge([
            'approval_type' => 'Village', 'name' => 'Green Meadows', 'location' => 'Vallam', 'district' => 'Thanjavur', 'state' => 'Tamil Nadu',
            'size_acres' => 5, 'guideline_rate' => 300, 'market_rate' => 650,
        ], $extra))->assertRedirect();

        return Project::withoutGlobalScopes()->latest('id')->first();
    }

    public function test_create_project_with_auto_code_and_values(): void
    {
        [$tenant, $admin] = $this->makeTenant('P Co', true);
        $p = $this->newProject($admin);
        $this->assertStringStartsWith('PRJ'.now()->format('dmY'), $p->project_code);
        $this->assertEquals(217800, $p->totalSqft());
        $this->assertEquals(217800 * 300, (float) $p->guideline_value);
        $this->assertEquals(217800 * 650, (float) $p->market_value);
        $this->assertSame('draft', $p->status);
    }

    public function test_project_limit_enforced(): void
    {
        [$tenant, $admin] = $this->makeTenant('Limit Co', true, 'starter'); // 2 projects
        $this->newProject($admin);
        $this->newProject($admin, ['name' => 'Second']);
        $this->actingAs($admin)->post(route('ws.projects.store'), ['approval_type' => 'Village', 'name' => 'Third', 'location' => 'X', 'district' => 'Y', 'state' => 'Tamil Nadu', 'size_acres' => 1])
            ->assertSessionHas('error');
        $this->assertSame(2, Project::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        // soft-deleted projects do not count
        Project::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()->delete();
        $this->newProject($admin, ['name' => 'Third']);
    }

    public function test_estimate_mrp_and_critical_path_duration(): void
    {
        [$tenant, $admin] = $this->makeTenant('E Co', true);
        $p = $this->newProject($admin);
        app(Tenancy::class)->set($tenant);
        $road = \App\Models\FacilityMaster::where('facility_code', 'F01')->first(); // village 450 / sq m
        app(Tenancy::class)->set(null);
        $this->actingAs($admin)->get(route('ws.estimate.edit', $p))->assertOk();
        $this->assertSame('init', $p->fresh()->status);
        $this->actingAs($admin)->post(route('ws.estimate.save', $p), [
            'tier' => 'Basic', 'sellable_pct' => 55, 'mrp_multiplier' => 3,
            'facility' => [$road->id => ['include' => 1, 'qty' => 1000, 'cost' => 450]],
            'stage' => [1 => ['name' => 'Land Due Diligence', 'cost' => 50000, 'include' => 1], 2 => ['name' => 'X', 'cost' => 99999, 'include' => 0]],
            'other' => [['description' => 'Misc', 'qty' => 1, 'cost' => 10000]],
        ])->assertRedirect();
        $e = $p->fresh()->estimate;
        $this->assertEquals(450000, (float) $e->facility_cost);
        $this->assertEquals(50000, (float) $e->stage_cost);
        $this->assertEquals(510000, (float) $e->total_cost);
        $this->assertEquals(round(217800 * 0.55, 2), (float) $e->sellable_sqft);
        $perSqft = round(510000 / (217800 * 0.55), 2);
        $this->assertEquals($perSqft, (float) $e->cost_per_sqft);
        $this->assertEquals(round($perSqft * 3, 2), (float) $e->mrp_per_sqft);
        $this->assertGreaterThan(100, $e->est_duration_days);
        $this->assertSame('go_no_go', $p->fresh()->status);
        $this->actingAs($admin)->get(route('ws.estimate.export', [$p, 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get(route('ws.estimate.export', [$p, 'xlsx']))->assertOk();
    }

    public function test_critical_path(): void
    {
        $cp = ProjectService::criticalPath([
            'A' => ['duration' => 5, 'deps' => []],
            'B' => ['duration' => 3, 'deps' => ['A']],
            'C' => ['duration' => 10, 'deps' => []],
            'D' => ['duration' => 2, 'deps' => ['B', 'C']],
        ]);
        $this->assertSame(12, $cp['total']);
        $this->assertSame(10, $cp['es']['D']);
    }

    public function test_no_go_closes_project_read_only(): void
    {
        [$tenant, $admin] = $this->makeTenant('N Co', true);
        $p = $this->newProject($admin);
        $this->actingAs($admin)->post(route('ws.estimate.save', $p), ['tier' => 'Basic', 'sellable_pct' => 55, 'mrp_multiplier' => 3]);
        $this->actingAs($admin)->post(route('ws.projects.decision', $p), ['decision' => 'no_go', 'notes' => 'Title issue'])->assertSessionHas('ok');
        $this->assertSame('closed', $p->fresh()->status);
        $this->actingAs($admin)->get(route('ws.projects.edit', $p))->assertForbidden();
    }

    public function test_start_tracking_dependencies_documents_and_progress(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant('T Co', true);
        $p = $this->newProject($admin);
        $this->actingAs($admin)->post(route('ws.estimate.save', $p), ['tier' => 'Basic', 'sellable_pct' => 55, 'mrp_multiplier' => 3, 'stage' => [1 => ['name' => 'S1', 'cost' => 10000, 'include' => 1]]]);
        $this->actingAs($admin)->post(route('ws.projects.decision', $p), ['decision' => 'go']);
        $this->actingAs($admin)->post(route('ws.projects.start', $p), ['start_date' => '2026-10-12'])->assertRedirect(route('ws.tracking.show', $p));
        $p->refresh();
        $this->assertSame('in_progress', $p->status);
        $this->assertSame(13, $p->stages()->count());
        $this->assertSame(49, $p->subtasks()->count());
        $this->assertNotNull($p->est_end_date);
        $this->assertTrue($p->est_end_date->isWeekday());

        app(Tenancy::class)->set($tenant);
        $v13 = ProjectSubtask::where('project_id', $p->id)->where('task_code', 'V1.3')->first();
        $v11 = ProjectSubtask::where('project_id', $p->id)->where('task_code', 'V1.1')->first();
        $v14 = ProjectSubtask::where('project_id', $p->id)->where('task_code', 'V1.4')->first();
        app(Tenancy::class)->set(null);
        $this->assertTrue($v13->planned_start->gt($v11->planned_start));

        // dependency not done → refused
        $this->actingAs($admin)->put(route('ws.tracking.update', [$p, $v14]), ['status' => 'done'])->assertSessionHas('error');
        $this->assertStringContainsString('V1.3', session('error'));
        // mandatory docs missing → refused
        $this->actingAs($admin)->put(route('ws.tracking.update', [$p, $v11]), ['status' => 'done'])->assertSessionHas('error');
        $this->assertStringContainsString('AGR-02', session('error'));
        // upload the mandatory docs, then Done works
        foreach ($v11->documents()->withoutGlobalScopes()->get() as $doc) {
            $this->actingAs($admin)->post(route('ws.tracking.upload', [$p, $doc]), ['file' => UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n".str_repeat('x', 2048))])->assertSessionHas('ok');
        }
        $this->actingAs($admin)->put(route('ws.tracking.update', [$p, $v11]), ['status' => 'done', 'actual_cost' => 2000])->assertSessionHas('ok');
        $this->assertSame('done', $v11->fresh()->status);
        $this->assertGreaterThan(0, (float) $p->fresh()->progress_pct);
        $this->assertGreaterThan(0, (float) $p->stages()->where('stage_no', 1)->first()->progress_pct);
        $this->assertGreaterThan(0, $tenant->fresh()->storage_used_bytes);

        // not ready to launch with open approvals
        $this->actingAs($admin)->post(route('ws.tracking.ready', $p))->assertSessionHas('error');
        $this->actingAs($admin)->get(route('ws.tracking.show', $p))->assertOk();
        $this->actingAs($admin)->get(route('ws.projects.show', $p))->assertOk();
    }

    public function test_ready_to_launch_when_all_approvals_done_and_budget_alerts(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant('R Co', true);
        $p = $this->newProject($admin);
        $this->actingAs($admin)->post(route('ws.estimate.save', $p), ['tier' => 'Basic', 'sellable_pct' => 55, 'mrp_multiplier' => 3, 'stage' => [1 => ['name' => 'S1', 'cost' => 10000, 'include' => 1]]]);
        $this->actingAs($admin)->post(route('ws.projects.decision', $p), ['decision' => 'go']);
        $this->actingAs($admin)->post(route('ws.projects.start', $p), ['start_date' => '2026-10-12']);
        app(Tenancy::class)->set($tenant);
        $p->refresh();
        // budget alert: spend 90% of stage 1 budget on one sub-task
        $stage1 = $p->stages()->where('stage_no', 1)->first();
        $sub = $stage1->subtasks()->first();
        $sub->update(['actual_cost' => 9000]);
        ProjectService::recalc($p);
        $this->assertArrayHasKey('stage:'.$stage1->id.'@80', $p->fresh()->budget_alerts_sent);
        Mail::assertQueued(\App\Mail\TemplateMail::class, fn ($m) => str_contains($m->mailSubject, 'Budget alert'));
        // complete everything except the final stage
        $last = $p->stages()->max('stage_no');
        \App\Models\ProjectSubtask::where('project_id', $p->id)->whereHas('stage', fn ($q) => $q->where('stage_no', '<', $last))->update(['status' => 'done']);
        app(Tenancy::class)->set(null);
        $this->actingAs($admin)->post(route('ws.tracking.ready', $p))->assertRedirect(route('ws.launch.index'));
        $this->assertSame('ready_to_launch', $p->fresh()->status);
    }

    public function test_sales_cannot_create_projects_and_other_tenant_project_404(): void
    {
        [$tenant, $admin] = $this->makeTenant('S Co', true);
        [$other, $otherAdmin] = $this->makeTenant('Other Co', true);
        $p = $this->newProject($otherAdmin);
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $this->actingAs($sales)->get(route('ws.projects.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('ws.projects.show', $p))->assertNotFound();
        $this->actingAs($admin)->put(route('ws.projects.update', $p), ['name' => 'x'])->assertNotFound();
    }
}
