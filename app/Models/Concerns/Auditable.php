<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Writes an audit_logs row on create/update/delete (OWASP A09).
 * Attributes listed in $hidden (passwords, encrypted IDs) are never logged.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => app(AuditLogger::class)->model('created', $model, [], $model->auditValues($model->getAttributes())));
        static::updated(function ($model) {
            $changed = $model->getChanges();
            unset($changed['updated_at']);
            if ($changed === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changed);
            app(AuditLogger::class)->model('updated', $model, $model->auditValues($old), $model->auditValues($changed));
        });
        static::deleted(fn ($model) => app(AuditLogger::class)->model('deleted', $model, $model->auditValues($model->getAttributes()), []));
    }

    protected function auditValues(array $values): array
    {
        $blocked = array_merge($this->getHidden(), ['password', 'remember_token', 'aadhaar', 'pan', 'bank_details']);

        return array_diff_key($values, array_flip($blocked));
    }
}
