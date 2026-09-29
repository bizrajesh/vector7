<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentWriter extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'licence_no', 'office', 'phone', 'email', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
