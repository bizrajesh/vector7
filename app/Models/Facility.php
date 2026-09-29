<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'unit', 'unit_cost', 'deduct_from_sellable', 'is_active'];

    protected function casts(): array
    {
        return ['deduct_from_sellable' => 'boolean', 'is_active' => 'boolean'];
    }
}
