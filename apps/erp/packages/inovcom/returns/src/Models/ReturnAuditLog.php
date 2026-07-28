<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;

class ReturnAuditLog extends TenantModel
{
    protected $table = 'returns_audit_logs';

    public $timestamps = true;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'changes',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'performed_at' => 'datetime',
    ];
}
