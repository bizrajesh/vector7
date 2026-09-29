<?php

namespace App\Services;

use App\Models\TenantSetting;
use Illuminate\Support\Arr;

/**
 * Per-tenant settings with defaults from config('vector7.tenant_defaults').
 */
class Settings
{
    private ?array $cache = null;

    public function all(): array
    {
        if ($this->cache === null) {
            $stored = TenantSetting::query()->pluck('value', 'key')->all();
            $this->cache = array_merge(config('vector7.tenant_defaults'), $stored);
        }

        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        abort_unless(array_key_exists($key, config('vector7.tenant_defaults')), 422, 'Unknown setting.');

        TenantSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->cache = null;
    }

    public function forget(): void
    {
        $this->cache = null;
    }
}
