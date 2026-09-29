<?php

namespace App\Services;

use App\Enums\LayoutStatus;
use App\Models\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Layout project lifecycle: Draft → Submitted → In progress → Ready to launch → Launched → Closed.
 */
class LayoutService
{
    public function __construct(
        private readonly EstimateService $estimates,
        private readonly StageService $stages,
        private readonly ShareService $shares,
    ) {}

    public function submit(Layout $layout): void
    {
        abort_unless($layout->status === LayoutStatus::Draft, 422, 'Only a draft can be submitted.');

        $missing = array_filter([
            'total area' => (float) $layout->total_sqft <= 0,
            'a stage group' => ! $layout->stages()->exists(),
            'at least one land owner' => ! $layout->owners()->exists(),
            'at least one survey number' => ! $layout->surveyNumbers()->exists(),
        ]);
        if ($missing) {
            throw ValidationException::withMessages(['submit' => 'Add '.implode(', ', array_keys($missing)).' before submitting.']);
        }

        DB::transaction(function () use ($layout) {
            $layout->status = LayoutStatus::Submitted;
            $layout->submitted_at = now();
            $layout->save();

            $this->stages->schedule($layout);
            $estimate = $this->estimates->snapshot($layout, lock: true);
            $this->shares->lockBaseline($layout, (float) $estimate->cost_per_sellable_sqft);
        });
    }

    public function launch(Layout $layout): void
    {
        abort_unless($layout->status === LayoutStatus::ReadyToLaunch, 422, 'Only a Ready-to-Launch project can be launched.');
        if (! $layout->plots()->exists()) {
            throw ValidationException::withMessages(['launch' => 'Import plots before launching.']);
        }

        $layout->status = LayoutStatus::Launched;
        $layout->launched_at = now();
        $layout->save();
    }

    public function close(Layout $layout): void
    {
        abort_unless($layout->status === LayoutStatus::Launched, 422, 'Only a launched project can be closed.');

        DB::transaction(function () use ($layout) {
            $this->shares->trueUp($layout);
            $layout->status = LayoutStatus::Closed;
            $layout->closed_at = now();
            $layout->save();
        });
    }
}
