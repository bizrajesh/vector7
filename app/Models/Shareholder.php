<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shareholder extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'email', 'pan', 'bank_details'];

    protected $hidden = ['pan', 'bank_details'];

    protected function casts(): array
    {
        return ['pan' => 'encrypted', 'bank_details' => 'encrypted'];
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(ShareIssuance::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ShareAllocation::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(SharePayout::class);
    }
}
