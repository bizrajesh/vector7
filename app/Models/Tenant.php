<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'gstin', 'address', 'city', 'state', 'phone', 'email', 'logo_path'];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }
}
