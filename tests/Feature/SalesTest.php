<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Expense;
use App\Models\Holiday;
use App\Models\Plot;
use App\Models\Project;
use App\Models\PromoCode;
use App\Models\Sale;
use App\Services\Scheduler;
use App\Services\SalesService;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesTest extends TestCase
{
    private function setupPlot(array $plotAttrs = []): array
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant('Sales Co '.uniqid());
        $p = LaunchTest::readyProject($tenant);
        $p->update(['status' => 'launched', 'launched_at' => now()]);
        $plot = app(Tenancy::class)->run($tenant, fn () => Plot::create(array_merge(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'plot_no' => '1', 'patta_number' => '12',
            'size_sqft' => 1000, 'facing' => 'East', 'rate_per_sqft' => 1000], $plotAttrs)));
        $sales = $this->makeUser($tenant, 'tenant_sales');

        return [$tenant, $admin, $sales, $plot->fresh(), $p];
    }

    private function pay(float $amount): array
    {
        return ['amount' => $amount, 'mode' => 'upi', 'reference_no' => 'UTR1', 'paid_on' => today()->toDateString()];
    }

    public function test_only_one_customer_can_book_a_plot(): void
    {
        [$tenant, , , $plot] = $this->setupPlot();
        $a = $this->makeCustomer();
        $b = $this->makeCustomer();
        SalesService::book($plot, $a, 'marketplace', null, '1.1.1.1');
        try {
            SalesService::book($plot, $b, 'marketplace', null, '1.1.1.1');
            $this->fail('second booking must fail');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no longer available', json_encode($e->errors()));
        }
        $this->assertSame(1, Booking::withoutGlobalScopes()->where('plot_id', $plot->id)->count());
        $this->assertSame('booked', $plot->fresh()->status);
        // the database unique constraint also blocks a second active lock
        $this->expectException(\Illuminate\Database\QueryException::class);
        \DB::table('bookings')->insert(['tenant_id' => $tenant->id, 'booking_no' => 'X', 'project_id' => $plot->project_id, 'plot_id' => $plot->id, 'customer_id' => $b->id,
            'booked_on' => today(), 'valid_till' => today(), 'actual_price' => 1, 'price_used' => 1, 'net_price' => 1, 'active_plot_lock' => $plot->id]);
    }

    public function test_marketplace_booking_requires_login_and_disclaimer(): void
    {
        [, , , $plot] = $this->setupPlot();
        $this->get(route('account.book', $plot))->assertRedirect(route('login'));
        $c = $this->makeCustomer();
        $this->actingAs($c, 'customer')->get(route('account.book', $plot))->assertOk()->assertSee('Confirm booking');
        $this->actingAs($c, 'customer')->post(route('account.book.store', $plot), [])->assertSessionHasErrors('disclaimer');
        $this->actingAs($c, 'customer')->post(route('account.book.store', $plot), ['disclaimer' => '1'])->assertRedirect(route('account.bookings'));
        $b = Booking::withoutGlobalScopes()->where('customer_id', $c->id)->first();
        $this->assertNotNull($b->disclaimer_accepted_at);
        $this->assertSame('marketplace', $b->source);
        $this->assertTrue($c->tenants()->where('tenants.id', $plot->tenant_id)->exists());
    }

    public function test_booking_validity_counts_working_days_and_expiry_releases_plot(): void
    {
        [$tenant, , , $plot] = $this->setupPlot();
        $tenant->setting()->update(['booking_validity_days' => 2]);
        Holiday::create(['tenant_id' => $tenant->id, 'date' => '2026-10-12', 'name' => 'Holiday']); // Monday
        Carbon::setTestNow('2026-10-09 10:00'); // Friday
        $c = $this->makeCustomer();
        $b = SalesService::book($plot, $c, 'workspace', null, null);
        $this->assertSame('2026-10-14', $b->valid_till->toDateString()); // Tue 13 (1), Wed 14 (2)

        Carbon::setTestNow('2026-10-13 09:00'); // previous working day of Wed 14 → reminder
        $r = Scheduler::bookings();
        $this->assertSame(1, $r['reminded']);
        Mail::assertQueued(\App\Mail\TemplateMail::class, fn ($m) => str_contains($m->mailSubject, 'expires on'));

        Carbon::setTestNow('2026-10-14 18:00'); // still valid on the last day
        $this->assertSame(0, Scheduler::bookings()['released']);
        Carbon::setTestNow('2026-10-15 01:00');
        $this->assertSame(1, Scheduler::bookings()['released']);
        $this->assertSame('expired', $b->fresh()->status);
        $this->assertNull($b->fresh()->active_plot_lock);
        $this->assertSame('available', $plot->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_sale_needs_30_percent_initial_payment_and_ror_only_at_zero_due(): void
    {
        [$tenant, , $sales, $plot] = $this->setupPlot();
        $c = $this->makeCustomer();
        $c->associateWith($tenant->id);
        // price 10,00,000; plan 30/60/10
        try {
            SalesService::initiateSale($plot, $c, $this->pay(299999), null, null, null, $sales->id);
            $this->fail('below 30% must fail');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('at least the 1st instalment', json_encode($e->errors()));
        }
        $this->assertSame('available', $plot->fresh()->status);
        $this->assertSame(0, Sale::withoutGlobalScopes()->count());

        $sale = SalesService::initiateSale($plot, $c, $this->pay(300000), null, null, null, $sales->id);
        $this->assertSame('sale_init', $sale->status);
        $this->assertSame('sale_init', $plot->fresh()->status);
        $this->assertEquals(700000, (float) $sale->due_amount);
        $this->assertCount(3, $sale->instalments);
        $this->assertSame('paid', $sale->instalments[0]->status);

        SalesService::recordPayment($sale, $this->pay(650000), $sales->id);
        $this->assertSame('sale_init', $sale->fresh()->status);   // still due → not ROR
        $this->assertSame('sale_init', $plot->fresh()->status);
        try {
            SalesService::recordPayment($sale->fresh(), $this->pay(60000), $sales->id);
            $this->fail('overpayment must fail');
        } catch (ValidationException) {
        }
        SalesService::recordPayment($sale->fresh(), $this->pay(50000), $sales->id);
        $this->assertSame('ror', $sale->fresh()->status);
        $this->assertSame('ror', $plot->fresh()->status);
        $this->assertEquals(0, (float) $sale->fresh()->due_amount);
        $this->assertSame(3, \App\Models\Receipt::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        Mail::assertQueued(\App\Mail\TemplateMail::class, fn ($m) => str_contains($m->mailSubject, 'Receipt') && count($m->attachments()) === 1);
    }

    public function test_booked_plot_can_only_be_sold_to_the_customer_who_booked(): void
    {
        [$tenant, , $sales, $plot] = $this->setupPlot();
        $a = $this->makeCustomer();
        $b = $this->makeCustomer();
        $b->associateWith($tenant->id);
        SalesService::book($plot, $a, 'workspace', null, null);
        try {
            SalesService::initiateSale($plot->fresh(), $b, $this->pay(300000), null, null, null, $sales->id);
            $this->fail('other customer must be refused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('booked by another customer', json_encode($e->errors()));
        }
        $sale = SalesService::initiateSale($plot->fresh(), $a, $this->pay(300000), null, null, null, $sales->id);
        $this->assertSame('converted', Booking::withoutGlobalScopes()->where('plot_id', $plot->id)->first()->status);
        $this->assertSame('booking', $sale->payments()->withoutGlobalScopes()->first()->kind);
    }

    public function test_offer_price_used_only_while_valid_and_stored_on_booking(): void
    {
        [$tenant, , , $plot] = $this->setupPlot(['offer_text' => 'Diwali', 'offer_rate_per_sqft' => 900, 'offer_valid_till' => today()->addDays(3)]);
        $b = SalesService::book($plot, $this->makeCustomer(), 'workspace', null, null);
        $this->assertEquals(1000000, (float) $b->actual_price);
        $this->assertEquals(900000, (float) $b->offer_price);
        $this->assertEquals(900000, (float) $b->net_price);
        // later price changes do not affect the booking
        $plot->update(['rate_per_sqft' => 2000, 'offer_rate_per_sqft' => null, 'offer_valid_till' => null]);
        $this->assertEquals(900000, (float) $b->fresh()->net_price);

        // offer expired → actual price
        [$t2, , , $plot2] = $this->setupPlot(['offer_text' => 'Old', 'offer_rate_per_sqft' => 900, 'offer_valid_till' => today()->addDay()]);
        $this->travel(2)->days();
        $b2 = SalesService::book($plot2, $this->makeCustomer(), 'workspace', null, null);
        $this->assertNull($b2->offer_price);
        $this->assertEquals(1000000, (float) $b2->net_price);
    }

    public function test_promo_code_applies_on_top_of_price_and_is_logged(): void
    {
        [$tenant, , , $plot] = $this->setupPlot(['offer_text' => 'Diwali', 'offer_rate_per_sqft' => 900, 'offer_valid_till' => today()->addDays(3)]);
        PromoCode::create(['tenant_id' => $tenant->id, 'code' => 'FEST5', 'discount_type' => 'percent', 'value' => 5, 'valid_from' => today(), 'valid_to' => today()->addDays(5), 'max_uses' => 1]);
        $b = SalesService::book($plot, $this->makeCustomer(), 'workspace', 'fest5', null);
        $this->assertEquals(45000, (float) $b->discount_amount);
        $this->assertEquals(855000, (float) $b->net_price);
        $this->assertSame(1, \App\Models\PromoCodeUse::withoutGlobalScopes()->count());
        // max uses reached
        $plot2 = app(Tenancy::class)->run($tenant, fn () => Plot::create(['tenant_id' => $tenant->id, 'project_id' => $plot->project_id, 'plot_no' => '2', 'patta_number' => '1', 'size_sqft' => 1000, 'facing' => 'East', 'rate_per_sqft' => 1000]));
        $this->expectException(ValidationException::class);
        SalesService::book($plot2, $this->makeCustomer(), 'workspace', 'FEST5', null);
    }

    public function test_refund_penalty_after_missed_window_needs_admin_approval(): void
    {
        [$tenant, $admin, $sales, $plot] = $this->setupPlot();
        $c = $this->makeCustomer();
        $c->associateWith($tenant->id);
        $sale = SalesService::initiateSale($plot, $c, $this->pay(400000), null, null, null, $sales->id);
        // window not yet missed
        $this->actingAs($sales)->post(route('ws.refunds.store', $sale))->assertSessionHas('error');
        // default rules: 1–15 days 5%, 16–30 days 10%, 31+ 20%
        $this->travelTo($sale->window_end->copy()->addDays(20));
        $q = SalesService::refundQuote($sale->fresh());
        $this->assertSame(20, $q['days_late']);
        $this->assertEquals(40000, $q['penalty']);
        $this->assertEquals(360000, $q['refund']);
        $this->actingAs($sales)->post(route('ws.refunds.store', $sale))->assertSessionHas('ok');
        $refund = \App\Models\Refund::withoutGlobalScopes()->first();
        $this->assertSame('refund_pending', $sale->fresh()->status);
        // Sales cannot approve; admin needs password
        $this->actingAs($sales)->post(route('ws.refunds.decide', $refund), ['decision' => 'approve', 'current_password' => 'Secret@123'])->assertForbidden();
        $this->actingAs($admin)->post(route('ws.refunds.decide', $refund), ['decision' => 'approve', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->post(route('ws.refunds.decide', $refund), ['decision' => 'approve', 'current_password' => 'Secret@123'])->assertSessionHas('ok');
        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertSame('refunded', $sale->fresh()->status);
        $this->assertSame('available', $plot->fresh()->status);
        $this->assertEquals(360000, (float) Expense::withoutGlobalScopes()->where('refund_id', $refund->id)->value('amount'));
        $this->actingAs($admin)->get(route('ws.refunds.note', $refund))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_workspace_booking_registers_new_customer_and_sales_screens(): void
    {
        [$tenant, $admin, $sales, $plot] = $this->setupPlot();
        $this->actingAs($sales)->post(route('ws.bookings.store'), ['plot_id' => $plot->id, 'new_name' => 'Walk In', 'new_email' => 'walkin@example.com', 'new_mobile' => '9876501234', 'disclaimer' => '1'])->assertRedirect();
        $b = Booking::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame('walkin@example.com', $b->customer->email);
        Mail::assertQueued(\App\Mail\TemplateMail::class, fn ($m) => str_contains($m->bodyText, 'Set your password'));
        $this->actingAs($sales)->post(route('ws.sales.store'), ['booking_id' => $b->id, 'amount' => 300000, 'mode' => 'cash', 'paid_on' => today()->toDateString(), 'disclaimer' => '1'])->assertRedirect();
        $sale = Sale::withoutGlobalScopes()->latest('id')->first();
        foreach ([route('ws.bookings.index'), route('ws.bookings.show', $b), route('ws.sales.index'), route('ws.sales.show', $sale), route('ws.sales.create'), route('ws.bookings.create'), route('ws.refunds.index')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $pay = $sale->payments()->withoutGlobalScopes()->first();
        $this->actingAs($sales)->get(route('ws.payments.receipt', $pay))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($sales)->get(route('ws.api.plots', ['project' => $plot->project_id, 'statuses' => 'available']))->assertOk();
        // customer sees it in their account; another customer cannot open the receipt
        $this->actingAs($b->customer, 'customer')->get(route('account.payments'))->assertOk()->assertSee($pay->transaction_no);
        $this->actingAs($b->customer, 'customer')->get(route('account.receipt', $pay->id))->assertOk();
        $other = $this->makeCustomer();
        $this->actingAs($other, 'customer')->get(route('account.receipt', $pay->id))->assertNotFound();
        foreach (['account.dashboard', 'account.bookings', 'account.purchases', 'account.documents', 'account.enquiries', 'account.tickets', 'account.profile', 'account.requirements'] as $r) {
            $this->actingAs($b->customer, 'customer')->get(route($r))->assertOk();
        }
    }
}
