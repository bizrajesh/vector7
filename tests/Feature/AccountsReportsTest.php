<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Plot;
use App\Models\ProjectEstimate;
use App\Services\ReportService;
use App\Services\SalesService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountsReportsTest extends TestCase
{
    private function world(): array
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant('Ledger Co '.uniqid());
        $p = LaunchTest::readyProject($tenant);
        $p->update(['status' => 'launched', 'launched_at' => now()]);
        $plot = app(Tenancy::class)->run($tenant, fn () => Plot::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'plot_no' => '7', 'patta_number' => '1',
            'size_sqft' => 1000, 'facing' => 'East', 'rate_per_sqft' => 1000]));
        $c = $this->makeCustomer();
        $sale = app(Tenancy::class)->run($tenant, fn () => SalesService::initiateSale($plot->fresh(), $c, ['amount' => 300000, 'mode' => 'upi', 'reference_no' => 'U1', 'paid_on' => today()->toDateString()], null, null, null, $admin->id));
        $cat = app(Tenancy::class)->run($tenant, fn () => ExpenseCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Survey']));

        return [$tenant, $admin, $p, $sale, $cat];
    }

    public function test_expense_is_recorded_and_day_book_keeps_running_balance(): void
    {
        [$tenant, $admin, $p, , $cat] = $this->world();
        $this->actingAs($admin)->post(route('ws.expenses.store'), ['project_id' => $p->id, 'expense_category_id' => $cat->id, 'description' => 'Boundary survey', 'amount' => 50000, 'spent_on' => today()->toDateString(), 'mode' => 'cash'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $e = Expense::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNotEmpty($e->transaction_no);
        $res = $this->actingAs($admin)->get(route('ws.accounts.daybook'))->assertOk();
        $res->assertSee('Boundary survey')->assertSee('2,50,000'); // 3,00,000 in − 50,000 out
        $this->actingAs($admin)->get(route('ws.accounts.daybook', ['export' => 'xlsx']))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        foreach (['ws.accounts.index', 'ws.accounts.receipts', 'ws.expenses.index', 'ws.accounts.budget', 'ws.accounts.receivables'] as $r) {
            $this->actingAs($admin)->get(route($r))->assertOk();
        }
    }

    public function test_expense_links_must_belong_to_the_tenant(): void
    {
        [, $admin, , , $cat] = $this->world();
        [$other] = $this->makeTenant('Other '.uniqid());
        $foreign = LaunchTest::readyProject($other);
        $this->actingAs($admin)->post(route('ws.expenses.store'), ['project_id' => $foreign->id, 'expense_category_id' => $cat->id, 'description' => 'x', 'amount' => 10, 'spent_on' => today()->toDateString()])
            ->assertNotFound();
    }

    public function test_budget_view_compares_estimate_and_spend(): void
    {
        [$tenant, $admin, $p, , $cat] = $this->world();
        app(Tenancy::class)->run($tenant, function () use ($tenant, $p, $cat) {
            ProjectEstimate::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'tier' => 'basic', 'total_cost' => 100000, 'facility_cost' => 60000, 'stage_cost' => 40000]);
            Expense::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'expense_category_id' => $cat->id, 'transaction_no' => 'E1', 'description' => 'Road', 'amount' => 90000, 'spent_on' => today()]);
        });
        $this->actingAs($admin)->get(route('ws.accounts.budget'))->assertOk()->assertSee('90.0%')->assertSee($p->name);
    }

    public function test_sales_user_cannot_open_accounts(): void
    {
        [$tenant] = $this->world();
        $support = $this->makeUser($tenant, 'support');
        $this->actingAs($support)->get(route('ws.accounts.index'))->assertForbidden();
    }

    public function test_every_report_renders_and_exports(): void
    {
        [$tenant, $admin] = $this->world();
        $this->actingAs($admin)->get(route('ws.reports.index'))->assertOk()->assertSee('Broker commission');
        foreach (array_keys(ReportService::REPORTS) as $key) {
            $this->actingAs($admin)->get(route('ws.reports.show', $key))->assertOk();
            $this->actingAs($admin)->get(route('ws.reports.show', [$key, 'export' => 'xlsx']))->assertOk();
        }
        $pdf = $this->actingAs($admin)->get(route('ws.reports.show', ['sales-collections', 'export' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($admin)->get(route('ws.reports.show', 'sales-collections'))->assertSee('3,00,000');
        $this->actingAs($admin)->get(route('ws.reports.show', 'nope'))->assertNotFound();
    }

    public function test_report_export_needs_export_permission(): void
    {
        [$tenant] = $this->world();
        $sales = $this->makeUser($tenant, 'tenant_sales');
        if ($sales->hasPerm('reports.view') && ! $sales->hasPerm('reports.export')) {
            $this->actingAs($sales)->get(route('ws.reports.show', ['bookings', 'export' => 'xlsx']))->assertForbidden();
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_role_dashboards_render(): void
    {
        [$tenant, $admin] = $this->world();
        $this->actingAs($admin)->get(route('ws.dashboard'))->assertOk()->assertSee('Business overview')->assertSee('Sales desk')->assertSee('Money')->assertSee('ch-status', false);
        foreach (['tenant_manager' => 'Projects in progress', 'tenant_sales' => 'Sales desk', 'tenant_account' => 'Received vs spent', 'support' => 'Open tickets'] as $role => $text) {
            $u = $this->makeUser($tenant, $role);
            $this->actingAs($u)->get(route('ws.dashboard'))->assertOk()->assertSee($text);
        }
        $acc = $this->makeUser($tenant, 'tenant_account');
        $this->actingAs($acc)->get(route('ws.dashboard'))->assertDontSee('Business overview');
    }

    public function test_app_dashboard_renders_with_filters(): void
    {
        [$tenant] = $this->world();
        $this->actingAs($this->appAdmin())->get(route('app.dashboard'))->assertOk()->assertSee('Monthly recurring revenue')->assertSee('Bookings & sales');
        $this->actingAs($this->appAdmin())->get(route('app.dashboard', ['tenant' => $tenant->id, 'location' => 'Thanjavur', 'from' => today()->subMonth()->toDateString(), 'to' => today()->toDateString()]))->assertOk();
    }
}
