<?php

namespace InovCom\Kernel\Traits;

use InovCom\Kernel\Models\AuditLog;

/**
 * Add this trait to any TenantModel to automatically log creates, updates,
 * and deletes to the audit_logs table.
 *
 * Fields excluded from change tracking (too noisy / security risk):
 *   password, remember_token, updated_at
 */
trait Auditable
{
    /** Fields never included in old_values / new_values. */
    protected array $auditExclude = ['password', 'remember_token', 'updated_at'];

    public static function bootAuditable(): void
    {
        static::created(function (self $model) {
            AuditLog::record($model, 'created', static::currentUserId(), [
                'new' => $model->getAuditableAttributes($model->getAttributes()),
            ]);
        });

        static::updated(function (self $model) {
            $dirty = $model->getAuditableAttributes($model->getDirty());
            if (empty($dirty)) {
                return; // Nothing worth logging changed.
            }
            $old = [];
            foreach (array_keys($dirty) as $key) {
                $old[$key] = $model->getOriginal($key);
            }
            AuditLog::record($model, 'updated', static::currentUserId(), [
                'old' => $old,
                'new' => $dirty,
            ]);
        });

        static::deleted(function (self $model) {
            AuditLog::record($model, 'deleted', static::currentUserId(), [
                'old' => $model->getAuditableAttributes($model->getAttributes()),
            ]);
        });
    }

    /** Remove excluded fields from an attribute array. */
    protected function getAuditableAttributes(array $attrs): array
    {
        $exclude = array_merge($this->auditExclude, $this->hidden ?? []);
        return array_diff_key($attrs, array_flip($exclude));
    }

    /**
     * Resolve the current authenticated user ID.
     * Tries tenant guard first, falls back to default guard, then null.
     */
    protected static function currentUserId(): ?int
    {
        try {
            return auth('tenant')->id() ?? auth()->id();
        } catch (\Throwable) {
            return null;
        }
    }
}
