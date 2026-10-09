<?php

namespace App\Services;

use App\Models\IdSequence;
use Illuminate\Support\Facades\DB;

/**
 * Tenant ID seeds (spec 7.3.8): prefix set by the tenant, rest from the clock.
 *   project, user        → prefix + DDMMYYYYmm   (mm = minutes)
 *   booking, sale,
 *   expense, income      → prefix + DDMMYYYYmmss
 * Generated inside a transaction; on clash a suffix -2, -3 … is added.
 */
class IdGenerator
{
    public const TYPES = [
        'project' => ['label' => 'Project ID', 'format' => 'dmYi', 'default' => 'PRJ', 'table' => 'projects', 'column' => 'project_code'],
        'user' => ['label' => 'User ID', 'format' => 'dmYi', 'default' => 'USR', 'table' => 'users', 'column' => 'user_code'],
        'booking' => ['label' => 'Booking ID', 'format' => 'dmYis', 'default' => 'BKG', 'table' => 'bookings', 'column' => 'booking_no'],
        'sale' => ['label' => 'Sale ID', 'format' => 'dmYis', 'default' => 'SAL', 'table' => 'sales', 'column' => 'sale_no'],
        'expense' => ['label' => 'Expense transaction ID', 'format' => 'dmYis', 'default' => 'EXP', 'table' => 'expenses', 'column' => 'transaction_no'],
        'income' => ['label' => 'Income transaction ID', 'format' => 'dmYis', 'default' => 'INC', 'table' => 'payments', 'column' => 'transaction_no'],
    ];

    public static function prefix(int $tenantId, string $type): string
    {
        return IdSequence::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', $type)->value('prefix')
            ?? self::TYPES[$type]['default'];
    }

    public static function next(int $tenantId, string $type): string
    {
        $cfg = self::TYPES[$type];
        $base = self::prefix($tenantId, $type).now()->format($cfg['format']);

        return DB::transaction(function () use ($cfg, $base, $tenantId) {
            $q = fn ($candidate) => DB::table($cfg['table'])->where('tenant_id', $tenantId)->where($cfg['column'], $candidate)->lockForUpdate()->exists();
            $candidate = $base;
            $i = 2;
            while ($q($candidate)) {
                $candidate = $base.'-'.$i++;
            }

            return $candidate;
        });
    }

    public static function seedDefaults(int $tenantId): void
    {
        foreach (self::TYPES as $type => $cfg) {
            IdSequence::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenantId, 'type' => $type], ['prefix' => $cfg['default']]);
        }
    }
}
