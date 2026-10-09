<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Holds the tenant of the current request. Set by the `tenant` middleware for
 * tenant users; null for App users, customers and public pages.
 */
class Tenancy
{
    private ?Tenant $tenant = null;

    private bool $bypass = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->bypass ? null : $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /** Run a callback as a given tenant (used by jobs, seeders and the scheduler). */
    public function run(?Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;
        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }

    /** Run without tenant filtering (system jobs only). */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypass;
        $this->bypass = true;
        try {
            return $callback();
        } finally {
            $this->bypass = $previous;
        }
    }
}
