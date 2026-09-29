<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['gateway', 'event_id', 'type', 'payload', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
