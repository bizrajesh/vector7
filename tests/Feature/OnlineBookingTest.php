<?php

namespace Tests\Feature;

use App\Enums\LayoutStatus;
use App\Enums\PlotStatus;
use App\Mail\PlainNotification;
use App\Models\Booking;
use App\Models\Layout;
use App\Models\Plot;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnlineBookingTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPlot(bool $public = true): array
    {
        $admin = $this->makeTenantAdmin('dev@example.test');

        return app(TenantContext::class)->run($admin->tenant, function () use ($public, $admin) {
            $layout = new Layout(['code' => 'GM', 'name' => 'Green Meadows', 'total_sqft' => 50000, 'sellable_pct' => 55, 'std_plot_sqft' => 1200, 'land_cost' => 0, 'contingency_pct' => 0, 'is_public' => $public]);
            $layout->status = LayoutStatus::Launched;
            $layout->save();
            $plot = new Plot(['plot_no' => 'GM-01', 'size_sqft' => 1200, 'rate_sqft' => 1450, 'cost' => 1740000]);
            $plot->layout_id = $layout->id;
            $plot->status = PlotStatus::Available;
            $plot->save();

            return [$admin->tenant, $layout, $plot];
        });
    }

    public function test_unpublished_project_is_not_public(): void
    {
        [$tenant] = $this->publishedPlot(false);
        $this->get("/projects/{$tenant->slug}/gm")->assertNotFound();
    }

    public function test_customer_can_hold_a_plot_with_an_email_code(): void
    {
        Mail::fake();
        [$tenant, $layout, $plot] = $this->publishedPlot();

        $this->get("/projects/{$tenant->slug}/gm")->assertOk()->assertSee('GM-01');

        $this->post("/book/{$tenant->slug}/gm/GM-01", [
            'name' => 'Buyer', 'phone' => '9876543210', 'email' => 'buyer@example.test', 'consent' => '1',
        ])->assertRedirect('/book/verify');

        $code = null;
        Mail::assertSent(PlainNotification::class, function ($mail) use (&$code) {
            preg_match('/(\d{6})/', $mail->subjectLine, $m);
            $code = $m[1] ?? null;

            return true;
        });

        $this->post('/book/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/book/verify', ['code' => $code])->assertRedirect('/portal/customer');
        $this->assertAuthenticated();

        app(TenantContext::class)->run($tenant, function () use ($plot) {
            $this->assertSame(PlotStatus::Booked, $plot->fresh()->status);
            $this->assertSame('pending', Booking::query()->where('plot_id', $plot->id)->value('status'));
        });

        $this->get('/portal/customer')->assertOk()->assertSee('Held online');
    }

    public function test_there_is_no_online_purchase_or_payment_route(): void
    {
        $uris = collect(app('router')->getRoutes())->filter(fn ($r) => ! str_starts_with($r->uri(), 'app/') && ! str_starts_with($r->uri(), 'platform/'))->map->uri()->implode(' ');

        $this->assertStringNotContainsString('sell', $uris);
        $this->assertStringNotContainsString('payments', $uris);
    }
}
