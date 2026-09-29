<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpersonationSession extends Model
{
    public $timestamps = false;

    protected $fillable = ['super_admin_id', 'tenant_id', 'target_user_id', 'reason', 'started_at', 'expires_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];
    }
}
