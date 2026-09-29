<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Holds the tenant for the current request or console iteration.
 * Every tenant-owned model filters on this id (deny by default when unset).
 */
final class TenantContext
{
    private ?int $tenantId = null;

    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenantId = $tenant?->id;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Run a callback inside another tenant's context (console jobs, sign-up).
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->set($previous);
        }
    }
}
