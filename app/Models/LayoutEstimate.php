<?php

namespace App\Models;


class LayoutEstimate extends TenantModel
{

    protected $fillable = ['version', 'stage_cost', 'facility_cost', 'land_cost', 'contingency_pct', 'total_cost', 'production_value', 'sellable_sqft', 'est_plots', 'cost_per_sellable_sqft', 'total_days', 'locked_at'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }
}
