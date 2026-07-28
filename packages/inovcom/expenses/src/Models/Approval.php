<?php

namespace InovCom\Expenses\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Approval extends TenantModel
{
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'comments',
        'rejection_reason',
        'approval_level',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
