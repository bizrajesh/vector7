<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'active', 'onboarding_step' => 1, 'storage_used_bytes' => 0, 'type' => 'organisation'];

    protected function casts(): array
    {
        return ['terms_accepted_at' => 'datetime'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest('id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)->withPivot('source')->withTimestamps();
    }

    public function notificationGroups(): HasMany
    {
        return $this->hasMany(NotificationGroup::class);
    }

    public function plan(): ?Plan
    {
        return $this->subscription?->plan;
    }

    public function setting(): TenantSetting
    {
        return $this->settings ?? TenantSetting::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $this->id]);
    }

    public function addressLine(): string
    {
        return collect([$this->address_line1, $this->address_line2, $this->village, $this->city, $this->district, $this->state, $this->pin])->filter()->implode(', ');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('files.public-logo', $this) : null;
    }

    /** Tenant ID: T + DDMMYYYY + mmss + 4 random digits, unique. */
    public static function generateCode(): string
    {
        do {
            $code = 'T'.now()->format('dmY').now()->format('is').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
