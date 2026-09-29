<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationGroup extends TenantModel
{
    use SoftDeletes;

    protected $fillable = ['name', 'channel_email', 'channel_whatsapp'];

    protected function casts(): array
    {
        return ['channel_email' => 'boolean', 'channel_whatsapp' => 'boolean'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(NotificationGroupMember::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(NotificationGroupEvent::class);
    }
}
