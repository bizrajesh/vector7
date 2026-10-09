<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use Auditable, BelongsToTenant;

    public const APP_ROLES = ['app_admin', 'app_manager'];

    public const TENANT_ROLES = ['tenant_admin', 'tenant_manager', 'tenant_sales', 'tenant_account', 'support'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Effective permission keys: custom list if set, otherwise the base role defaults. */
    public function permissionKeys(): array
    {
        $base = config('permissions.roles.'.$this->base_role, []);
        if (is_array($this->permissions)) {
            // Custom roles can only combine permissions that exist for tenant modules.
            $allowed = array_keys(self::tenantPermissionCatalog());

            return array_values(array_intersect($this->permissions, $allowed));
        }

        return $base;
    }

    public function label(): string
    {
        return $this->name;
    }

    public static function tenantPermissionCatalog(): array
    {
        $out = [];
        foreach (config('permissions.tenant_modules') as $module => $label) {
            foreach (config('permissions.actions') as $action) {
                $out["$module.$action"] = "$label – ".ucfirst($action);
            }
        }

        return $out;
    }

    public static function systemRole(string $baseRole, ?int $tenantId): self
    {
        return static::where('tenant_id', $tenantId)->where('base_role', $baseRole)->where('is_system', true)->firstOrFail();
    }

    /** Create the five system roles for a new tenant. */
    public static function seedTenantRoles(int $tenantId): void
    {
        foreach (self::TENANT_ROLES as $base) {
            static::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => str_replace('_', '-', $base)],
                ['name' => config('permissions.role_labels.'.$base), 'base_role' => $base, 'is_system' => true]
            );
        }
    }
}
