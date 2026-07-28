<?php

namespace InovCom\Returns\Services;

use Illuminate\Support\Facades\Schema;
use InovCom\Returns\Models\ReturnAuditLog;

class AuditLogger
{
    /**
     * @param array<string, mixed> $changes
     */
    public function log(string $entityType, int $entityId, string $action, array $changes = [], ?int $userId = null): void
    {
        if (! Schema::connection('tenant')->hasTable('returns_audit_logs')) {
            return;
        }

        ReturnAuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'changes' => $changes ?: null,
            'performed_by' => $userId ?? auth('tenant')->id(),
            'performed_at' => now(),
        ]);
    }
}
