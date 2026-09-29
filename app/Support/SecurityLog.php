<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Security event logging (OWASP A09). Never pass passwords, tokens or ID numbers here.
 */
final class SecurityLog
{
    public static function info(string $event, array $context = []): void
    {
        Log::channel('security')->info($event, self::enrich($context));
    }

    public static function warning(string $event, array $context = []): void
    {
        Log::channel('security')->warning($event, self::enrich($context));
    }

    private static function enrich(array $context): array
    {
        $request = request();

        return $context + [
            'ip' => $request?->ip(),
            'ua' => substr((string) $request?->userAgent(), 0, 180),
            'tenant_id' => app(TenantContext::class)->id(),
        ];
    }
}
