<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Buyer testimonials — shown on the marketplace only after App moderation (status = approved). */
class Testimonial extends Model
{
    protected $guarded = ['id'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
