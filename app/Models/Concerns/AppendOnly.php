<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Financial and share records are immutable once written (OWASP A08 integrity).
 * Corrections are made with a new reversal row, never by editing or deleting.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' is append-only.'));
        static::deleting(fn () => throw new LogicException(static::class.' is append-only.'));
    }
}
