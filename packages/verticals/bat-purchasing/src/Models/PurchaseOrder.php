<?php

namespace InovCom\Achats\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use InovCom\Kernel\Traits\WorkflowStateMachine;

class PurchaseOrder extends TenantModel
{
    use Auditable, WorkflowStateMachine;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'code', 'supplier_id', 'project_id', 'status',
        'validated_at', 'validated_by', 'ordered_at', 'received_at',
        'total_ht', 'notes',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'ordered_at'   => 'date',
        'received_at'  => 'date',
        'total_ht'     => 'decimal:2',
    ];

    // ── WorkflowStateMachine ──────────────────────────────────────────
    public function allowedTransitions(): array
    {
        return [
            'draft'              => ['pending_validation', 'cancelled'],
            'pending_validation' => ['validated', 'draft', 'cancelled'],
            'validated'          => ['ordered', 'cancelled'],
            'ordered'            => ['received', 'partially_received', 'cancelled'],
            'partially_received' => ['received', 'cancelled'],
            'received'           => [],     // terminal
            'cancelled'          => [],     // terminal
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ── Relationships ─────────────────────────────────────────────────
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function project()
    {
        return $this->belongsTo(\InovCom\Projets\Models\Project::class, 'project_id');
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id')->orderBy('position');
    }
}
