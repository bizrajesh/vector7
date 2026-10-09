<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\Notify;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Marketplace customer (common user). Belongs to the App workspace. */
class Customer extends Authenticatable
{
    use Auditable, Notifiable;

    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'must_change_password' => false, 'failed_logins' => 0];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('source')->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CustomerRequirement::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /** Link the customer to a tenant (read-only visibility for that tenant). */
    public function associateWith(int $tenantId, string $source = 'booking'): void
    {
        $this->tenants()->syncWithoutDetaching([$tenantId => ['source' => $source]]);
    }

    public function sendPasswordResetNotification($token): void
    {
        Notify::send('password_reset', [$this->email], [
            'name' => $this->name,
            'link' => route('customer.password.reset', ['token' => $token, 'email' => $this->email]),
        ], null, now: true);
    }
}
