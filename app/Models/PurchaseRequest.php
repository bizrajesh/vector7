<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer's request to buy (or be called back) — purchases are always completed by the sales team. */
class PurchaseRequest extends TenantModel
{
    protected $fillable = ['plot_id', 'layout_id', 'customer_id', 'booking_id', 'name', 'phone', 'email', 'message', 'type', 'source'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
