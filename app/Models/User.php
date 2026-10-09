<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Notify;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Staff user. tenant_id NULL = App user (App Admin / App Manager);
 * otherwise a tenant user restricted to that tenant.
 */
class User extends Authenticatable
{
    use Auditable, BelongsToTenant, Notifiable;

    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'must_change_password' => false, 'failed_logins' => 0];

    protected $hidden = ['password', 'remember_token'];

    private ?array $permCache = null;

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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(NotificationGroup::class);
    }

    public function scopeOfTenant(Builder $q, ?int $tenantId): Builder
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function isAppUser(): bool
    {
        return $this->tenant_id === null;
    }

    public function baseRole(): string
    {
        return $this->role?->base_role ?? '';
    }

    public function isAppAdmin(): bool
    {
        return $this->baseRole() === 'app_admin';
    }

    public function isTenantAdmin(): bool
    {
        return $this->baseRole() === 'tenant_admin';
    }

    /** Admin = App Admin or Tenant Admin (sees unmasked PAN, approves refunds). */
    public function isAdmin(): bool
    {
        return in_array($this->baseRole(), ['app_admin', 'tenant_admin'], true);
    }

    public function hasPerm(string $key): bool
    {
        if ($this->permCache === null) {
            $this->permCache = array_flip($this->role?->permissionKeys() ?? []);
        }

        return isset($this->permCache[$key]);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function homeRoute(): string
    {
        return $this->isAppUser() ? route('app.dashboard') : route('ws.dashboard');
    }

    public function sendPasswordResetNotification($token): void
    {
        Notify::send('password_reset', [$this->email], [
            'name' => $this->name,
            'link' => route('password.reset', ['token' => $token, 'email' => $this->email]),
        ], $this->tenant_id, now: true);
    }

    /**
     * Who may generate a password for whom (spec 4.5):
     * App Admin → anyone; Tenant Admin → users of own tenant; Support → own-tenant users except Tenant Admins.
     */
    public function canGeneratePasswordFor(User|Customer $target): bool
    {
        if ($this->isAppAdmin()) {
            return true;
        }
        if ($target instanceof Customer || $target->isAppUser() || $target->tenant_id !== $this->tenant_id || $this->isAppUser()) {
            return false;
        }
        if ($this->isTenantAdmin()) {
            return true;
        }
        if ($this->baseRole() === 'support') {
            return $target->baseRole() !== 'tenant_admin';
        }

        return false;
    }
}
