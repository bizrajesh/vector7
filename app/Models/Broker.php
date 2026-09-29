<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Broker extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'email', 'pan', 'commission_pct', 'is_active'];

    protected $hidden = ['pan'];

    protected function casts(): array
    {
        return ['pan' => 'encrypted', 'is_active' => 'boolean'];
    }
}
