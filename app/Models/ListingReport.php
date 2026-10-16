<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingReport extends Model
{
    use BelongsToTenant;

    public const REASONS = ['wrong_price' => 'Wrong price', 'sold' => 'Already sold', 'fake' => 'Fake listing', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class)->withTrashed(); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
