<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['email', 'purpose', 'code_hash', 'expires_at', 'ip_address'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
