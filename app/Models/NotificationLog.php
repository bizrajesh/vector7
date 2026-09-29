<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'channel', 'recipient', 'event_code', 'subject', 'status', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
