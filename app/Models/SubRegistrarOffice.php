<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class SubRegistrarOffice extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'district'];

}
