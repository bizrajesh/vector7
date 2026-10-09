<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Writes an audit_logs row (who, what, when, before/after) for create, update and delete.
 * Hidden attributes (passwords, tokens, secrets) are never written.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => AuditLogger::model('created', $m, null, $m->auditPayload($m->getAttributes())));

        static::updated(function ($m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if (! $changes) {
                return;
            }
            $before = array_intersect_key($m->getOriginal(), $changes);
            $action = array_key_exists('status', $changes) ? 'status_changed' : 'updated';
            AuditLogger::model($action, $m, $m->auditPayload($before), $m->auditPayload($changes));
        });

        static::deleted(fn ($m) => AuditLogger::model('deleted', $m, $m->auditPayload($m->getAttributes()), null));
    }

    public function auditPayload(array $attributes): array
    {
        $hidden = array_merge($this->getHidden(), ['password', 'remember_token', 'access_token', 'pan_encrypted']);

        return array_diff_key($attributes, array_flip($hidden));
    }
}
