<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationGroupMember extends TenantModel
{

    protected $fillable = ['user_id', 'name', 'email', 'phone'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
