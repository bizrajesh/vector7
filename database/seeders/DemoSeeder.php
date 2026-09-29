<?php

namespace Database\Seeders;

use App\Enums\LayoutStatus;
use App\Enums\PlotStatus;
use App\Models\Layout;
use App\Models\LayoutOwner;
use App\Models\LayoutSurveyNumber;
use App\Models\Plan;
use App\Models\Plot;
use App\Models\StageGroup;
use App\Models\Tenant;
use App\Services\StageService;
use App\Services\TenantProvisioner;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * STAGING ONLY: php artisan db:seed --class=DemoSeeder
 * Creates "Demo Realty" with an Admin, one layout (Ready to Launch) and 20 plots.
 * The Admin password is random and printed once.
 */
class DemoSeeder extends Seeder
{
    public function run(TenantProvisioner $provisioner, TenantContext $context): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoSeeder refuses to run in production.');

            return;
        }

        $password = Str::password(16);
        $admin = $provisioner->register([
            'business_name' => 'Demo Realty', 'name' => 'Demo Admin', 'email' => 'admin@demo.vector7.test',
            'phone' => '9800000000', 'password' => $password,
        ], Plan::query()->where('code', 'growth')->firstOrFail(), 'yearly');
        $admin->forceFill(['email_verified_at' => now()])->save();

        $context->run(Tenant::findOrFail($admin->tenant_id), function () {
            $layout = new Layout([
                'code' => 'LP-001', 'name' => 'Green Meadows', 'location' => 'Vallam Road', 'district' => 'Thanjavur',
                'total_sqft' => 435600, 'sellable_pct' => 55, 'std_plot_sqft' => 1200, 'land_cost' => 12000000,
                'contingency_pct' => 5, 'default_rate_sqft' => 1450,
            ]);
            $layout->status = LayoutStatus::Draft;
            $layout->save();

            $owner = new LayoutOwner(['name' => 'Sample Owner', 'phone' => '9800000001']);
            $owner->layout_id = $layout->id;
            $owner->save();
            $survey = new LayoutSurveyNumber(['survey_no' => '142/3B', 'extent_sqft' => 435600, 'guideline_value_sqft' => 250]);
            $survey->layout_id = $layout->id;
            $survey->save();

            app(StageService::class)->instantiate($layout, StageGroup::query()->firstOrFail());
            $layout->stages()->update(['status' => 'completed', 'progress_pct' => 100]);
            $layout->forceFill(['status' => LayoutStatus::ReadyToLaunch, 'submitted_at' => now()])->save();

            foreach (range(1, 20) as $n) {
                $plot = new Plot([
                    'plot_no' => sprintf('GM-%02d', $n), 'survey_no' => '142/3B', 'size_sqft' => 1200,
                    'rate_sqft' => 1450, 'cost' => 1740000, 'facing' => $n % 2 ? 'east' : 'west', 'dimensions' => '30x40',
                ]);
                $plot->layout_id = $layout->id;
                $plot->status = PlotStatus::Available;
                $plot->save();
            }
        });

        $this->command->info('Demo tenant ready. Admin: admin@demo.vector7.test  Password (shown once): '.$password);
    }
}
