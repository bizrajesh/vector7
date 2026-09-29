<?php

namespace App\Models;

class LedgerCategory extends TenantModel
{
    protected $fillable = ['name', 'direction'];
}
