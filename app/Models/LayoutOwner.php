<?php

namespace App\Models;


class LayoutOwner extends TenantModel
{

    protected $fillable = ['name', 'father_name', 'address', 'phone', 'aadhaar', 'pan', 'share_pct'];

    protected $hidden = ['aadhaar', 'pan'];

    protected function casts(): array
    {
        return ['aadhaar' => 'encrypted', 'pan' => 'encrypted'];
    }
}
