<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use App\Support\TenantContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Auditable;
    use Notifiable;
    use SoftDeletes;

    // role, status, tenant_id and links are set explicitly by services, never mass-assigned.
    protected $fillable = ['name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Users of the tenant in context only (users are not globally scoped because login needs them). */
    public function scopeInCurrentTenant(Builder $query): Builder
    {
        return $query->where('tenant_id', app(TenantContext::class)->id() ?? 0);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin && $this->tenant_id === null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role->value, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, config('vector7.permissions.'.$this->role->value, []), true);
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            Role::SuperAdmin => route('platform.dashboard'),
            Role::Shareholder => route('portal.shareholder'),
            Role::Customer => route('portal.customer'),
            default => route('app.dashboard'),
        };
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
