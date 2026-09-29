<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistTemplate extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'purpose'];

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('sort_order');
    }
}
