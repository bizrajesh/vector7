<?php

namespace App\Models;

class TenantSetting extends TenantModel
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
