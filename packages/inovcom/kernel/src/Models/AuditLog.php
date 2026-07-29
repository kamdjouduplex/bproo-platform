<?php

namespace InovCom\Kernel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log for all business entity changes.
 * Stored in the tenant database (connection = 'tenant').
 */
class AuditLog extends Model
{
    protected $connection = 'tenant';
    protected $table      = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'user_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Write an audit record. Silently swallowed if the table does not exist yet
     * (e.g. during tenant provisioning before migrations have run).
     */
    public static function record(
        Model  $model,
        string $event,
        ?int   $userId,
        array  $extra = []
    ): void {
        try {
            static::create([
                'auditable_type' => $model->getTable(),
                'auditable_id'   => $model->getKey(),
                'event'          => $event,
                'user_id'        => $userId,
                'old_values'     => $extra['old'] ?? null,
                'new_values'     => $extra['new'] ?? null,
                'ip_address'     => request()->ip(),
                'user_agent'     => substr((string) request()->userAgent(), 0, 255),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // Silently ignore if table not available yet.
        }
    }
}
