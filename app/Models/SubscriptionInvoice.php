<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    protected $fillable = ['tenant_id', 'subscription_id', 'invoice_no', 'amount', 'gst_amount', 'total', 'status', 'gateway_payment_id', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
