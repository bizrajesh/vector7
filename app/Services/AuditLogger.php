<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function __construct(private readonly TenantContext $context) {}

    public function model(string $action, Model $model, array $old, array $new): void
    {
        $this->write($action, $model::class, $model->getKey(), $old, $new, $model->getAttribute('tenant_id'));
    }

    public function event(string $action, array $data = [], ?int $tenantId = null): void
    {
        $this->write($action, null, null, [], $data, $tenantId);
    }

    private function write(string $action, ?string $type, mixed $id, array $old, array $new, mixed $tenantId): void
    {
        $request = request();

        AuditLog::create([
            'tenant_id' => $tenantId ?? $this->context->id(),
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $type ? class_basename($type) : null,
            'auditable_id' => $id,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
        ]);
    }
}
