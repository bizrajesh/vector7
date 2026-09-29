<?php

namespace App\Models;


class Holiday extends TenantModel
{

    protected $fillable = ['holiday_date', 'name'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }
}
