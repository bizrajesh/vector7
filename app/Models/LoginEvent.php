<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One sign-in attempt (success / failed / locked / blocked) — App security monitoring. */
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    public const PORTALS = ['buyer' => 'Buyer', 'promoter' => 'Promoter workspace', 'lister' => 'Property lister', 'app' => 'App team'];

    public const OUTCOMES = ['success' => 'Signed in', 'failed' => 'Wrong password / unknown', 'locked' => 'Account locked', 'blocked' => 'Blocked'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function tenantRel() { return $this->belongsTo(Tenant::class, 'tenant_id'); }
}
