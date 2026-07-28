<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;

class ReturnApprovalLog extends TenantModel
{
    protected $table = 'return_approval_logs';

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'decision',
        'reason',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function decider()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'decided_by');
    }
}
