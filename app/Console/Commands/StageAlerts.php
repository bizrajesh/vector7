<?php

namespace App\Console\Commands;

use App\Models\ProjectStage;
use App\Models\Tenant;
use App\Services\Notifier;
use App\Services\StageService;
use App\Services\Settings;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class StageAlerts extends Command
{
    protected $signature = 'vector7:stage-alerts';

    protected $description = 'Overdue stages and budget-threshold alerts';

    public function handle(TenantContext $context): int
    {
        Tenant::query()->whereIn('status', ['trial', 'active', 'past_due'])->each(function (Tenant $tenant) use ($context) {
            $context->run($tenant, function () {
                ProjectStage::query()->whereIn('status', ['pending', 'in_progress'])
                    ->whereHas('layout', fn ($q) => $q->whereIn('status', ['submitted', 'in_progress']))
                    ->with('layout')->get()
                    ->each(function (ProjectStage $stage) {
                        if ($stage->isOverdue()) {
                            app(Notifier::class)->event('stage.overdue', "Stage {$stage->name} on {$stage->layout->name} is overdue (planned end {$stage->planned_end->format('d M Y')}).", $stage->layout);
                        }
                        app(StageService::class)->checkBudget($stage);
                    });
            });
            app(Settings::class)->forget();
        });

        return self::SUCCESS;
    }
}
