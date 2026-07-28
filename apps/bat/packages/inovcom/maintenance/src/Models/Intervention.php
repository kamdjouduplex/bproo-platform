<?php

namespace InovCom\Maintenance\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use InovCom\Kernel\Traits\WorkflowStateMachine;

class Intervention extends TenantModel
{
    use Auditable, WorkflowStateMachine;

    protected $table = 'interventions';

    protected $fillable = [
        'maintenance_order_id', 'technician_id', 'status',
        'scheduled_at', 'started_at', 'completed_at', 'duration_minutes',
        'work_done', 'findings', 'next_action',
        'client_signature', 'signed_at',
        'photos', 'materials_used',
    ];

    protected $casts = [
        'scheduled_at'  => 'datetime',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'signed_at'     => 'datetime',
        'photos'        => 'array',
        'materials_used' => 'array',
    ];

    // ── WorkflowStateMachine ──────────────────────────────────────────
    public function allowedTransitions(): array
    {
        return [
            'scheduled'   => ['in_progress'],
            'in_progress' => ['done'],
            'done'        => [],
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────
    public function order()
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function technician()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'technician_id');
    }

    // ── Business logic ────────────────────────────────────────────────

    /**
     * Auto-compute duration from started_at → completed_at if not set manually.
     */
    public function computeDuration(): int
    {
        if ($this->started_at && $this->completed_at) {
            return (int) $this->started_at->diffInMinutes($this->completed_at);
        }
        return 0;
    }

    /**
     * Total materials cost (sum of qty * unit_price).
     */
    public function materialsCost(): float
    {
        $materials = $this->materials_used ?? [];
        return collect($materials)->sum(fn ($m) => ($m['qty'] ?? 0) * ($m['unit_price'] ?? 0));
    }
}
