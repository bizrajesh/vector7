<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buyer's paid "view contact" for one listing or layout. The money goes to the App owner, not the promoter. */
class ContactUnlock extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'paid_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function tenantRel(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function listing(): BelongsTo { return $this->belongsTo(Listing::class)->withoutGlobalScopes(); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class)->withoutGlobalScopes(); }

    public function scopeValid(Builder $q): Builder
    {
        return $q->where('status', 'paid')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function kind(): string { return $this->listing_id ? 'listing' : 'project'; }
}
