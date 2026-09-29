<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StageGroup extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(StageTemplate::class)->orderBy('seq_no');
    }
}
