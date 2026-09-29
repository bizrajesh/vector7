<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BookingService;
use App\Services\Settings;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class ExpireBookings extends Command
{
    protected $signature = 'vector7:expire-bookings';

    protected $description = 'Release bookings that passed their validity without converting to a sale';

    public function handle(TenantContext $context): int
    {
        $total = 0;
        Tenant::query()->whereIn('status', ['trial', 'active', 'past_due'])->each(function (Tenant $tenant) use ($context, &$total) {
            $total += $context->run($tenant, fn () => app(BookingService::class)->expireDue());
            app(Settings::class)->forget();
        });

        $this->info("Expired {$total} bookings.");

        return self::SUCCESS;
    }
}
