<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function model(string $action, Model $model, ?array $before, ?array $after): void
    {
        self::log($action, $model, $before, $after, $model->getAttribute('tenant_id'));
    }

    public static function log(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?int $tenantId = null): void
    {
        [$type, $id, $name] = self::actor();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        AuditLog::create([
            'tenant_id' => $tenantId ?? app(Tenancy::class)->id(),
            'actor_type' => $type,
            'actor_id' => $id,
            'actor_name' => $name,
            'action' => $action,
            'auditable_type' => $subject ? class_basename($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'before' => $before ? self::clean($before) : null,
            'after' => $after ? self::clean($after) : null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    private static function actor(): array
    {
        $user = Auth::guard('web')->user();
        if ($user instanceof User) {
            return ['user', $user->id, $user->name];
        }
        $customer = Auth::guard('customer')->user();
        if ($customer instanceof Customer) {
            return ['customer', $customer->id, $customer->name];
        }

        return ['system', null, 'System'];
    }

    private static function clean(array $data): array
    {
        foreach ($data as $k => $v) {
            if ($v instanceof \DateTimeInterface) {
                $data[$k] = $v->format('Y-m-d H:i:s');
            } elseif (is_string($v) && strlen($v) > 2000) {
                $data[$k] = substr($v, 0, 2000).'…';
            }
        }

        return $data;
    }
}
