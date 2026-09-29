<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SaleInstalment;
use App\Models\Tenant;
use App\Services\Notifier;
use App\Services\Settings;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'vector7:send-reminders';

    protected $description = 'Booking-expiry and instalment due/overdue reminders';

    public function handle(TenantContext $context): int
    {
        Tenant::query()->whereIn('status', ['trial', 'active', 'past_due'])->each(function (Tenant $tenant) use ($context) {
            $context->run($tenant, function () {
                $notifier = app(Notifier::class);

                // Bookings: reminders on day 10 and day 14 of a 15-day hold (i.e. 5 and 1 day(s) left).
                Booking::query()->where('status', 'active')->with(['plot.layout', 'customer'])->get()
                    ->filter(fn (Booking $b) => in_array($b->daysLeft(), [5, 1], true))
                    ->each(function (Booking $b) use ($notifier) {
                        $msg = "Booking for plot {$b->plot->plot_no} expires on {$b->expires_at->format('d M Y')}.";
                        $notifier->event('booking.expiring', $msg, $b->plot->layout);
                        $notifier->customer($b->customer, 'booking.expiring', $msg.' Please complete the purchase to keep the plot.');
                    });

                SaleInstalment::query()->whereIn('status', ['due', 'partial'])
                    ->whereHas('sale', fn ($q) => $q->where('status', 'ongoing'))
                    ->where('due_date', '<=', now()->addDay()->toDateString())
                    ->with('sale.customer', 'sale.plot.layout')->get()
                    ->each(function (SaleInstalment $i) use ($notifier) {
                        $overdue = $i->due_date->isPast() && ! $i->due_date->isToday();
                        $code = $overdue ? 'instalment.overdue' : 'instalment.due';
                        $msg = sprintf('Instalment %d for plot %s: ₹%s %s %s.', $i->seq, $i->sale->plot->plot_no, number_format($i->balance(), 2), $overdue ? 'was due on' : 'is due on', $i->due_date->format('d M Y'));
                        $notifier->event($code, $msg, $i->sale->plot->layout);
                        $notifier->customer($i->sale->customer, $code, $msg);
                    });
            });
            app(Settings::class)->forget();
        });

        return self::SUCCESS;
    }
}
