<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subscription extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'alerts_sent' => 'array'];
    }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function invoices(): HasMany { return $this->hasMany(SubscriptionInvoice::class); }
    public function daysLeft(): int { return max(0, (int) now()->startOfDay()->diffInDays($this->ends_on, false)); }
    public function isUsable(): bool { return in_array($this->status, ['trial', 'active', 'past_due'], true); }
}
