<?php

namespace App\Services;

use App\Models\Layout;
use App\Models\LayoutEstimate;

/**
 * Project estimate (section 5, steps 9–11): cost, production value, sellable units.
 */
class EstimateService
{
    public function __construct(private readonly StageService $stages) {}

    public function compute(Layout $layout): array
    {
        $stageCost = (float) $layout->stages()->where('status', '!=', 'skipped')->sum('budget_cost');
        $facilityCost = (float) $layout->facilities()->sum('total');
        $landCost = (float) $layout->land_cost;
        $totalCost = $stageCost + $facilityCost + $landCost;
        $production = round($totalCost * (1 + (float) $layout->contingency_pct / 100), 2);
        $sellable = round($layout->sellableSqft(), 2);
        $stdPlot = max(1.0, (float) $layout->std_plot_sqft);

        return [
            'stage_cost' => round($stageCost, 2),
            'facility_cost' => round($facilityCost, 2),
            'land_cost' => round($landCost, 2),
            'contingency_pct' => (float) $layout->contingency_pct,
            'total_cost' => round($totalCost, 2),
            'production_value' => $production,
            'sellable_sqft' => $sellable,
            'est_plots' => (int) floor($sellable / $stdPlot),
            'cost_per_sellable_sqft' => $sellable > 0 ? round($production / $sellable, 4) : 0,
            'total_days' => $this->stages->totalDays($layout),
        ];
    }

    public function snapshot(Layout $layout, bool $lock): LayoutEstimate
    {
        $version = (int) $layout->estimates()->max('version') + 1;

        $estimate = new LayoutEstimate($this->compute($layout) + ['version' => $version]);
        $estimate->layout_id = $layout->id;
        $estimate->locked_at = $lock ? now() : null;
        $estimate->created_by = auth()->id();
        $estimate->save();

        return $estimate;
    }
}
