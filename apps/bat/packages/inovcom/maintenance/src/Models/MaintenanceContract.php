<?php

namespace InovCom\Maintenance\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use InovCom\Kernel\Traits\WorkflowStateMachine;

class MaintenanceContract extends TenantModel
{
    use Auditable, WorkflowStateMachine;

    protected $table = 'maintenance_contracts';

    protected $fillable = [
        'code', 'client_id', 'quote_id', 'offer_id', 'title', 'type', 'status',
        'start_date', 'end_date',
        'price_per_month', 'response_time', 'resolution_time',
        'billing_cycle', 'intervention_frequency',
        'next_intervention_at', 'last_intervention_at', 'auto_generate_orders',
        'sites', 'terms',
        'suspended_at', 'expired_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'next_intervention_at' => 'date',
        'last_intervention_at' => 'date',
        'auto_generate_orders' => 'boolean',
        'price_per_month' => 'decimal:2',
        'sites'        => 'array',
        'suspended_at' => 'datetime',
        'expired_at'   => 'datetime',
    ];

    // ── WorkflowStateMachine ──────────────────────────────────────────
    public function allowedTransitions(): array
    {
        return [
            'active'    => ['suspended', 'expired'],
            'suspended' => ['active', 'expired'],
            'expired'   => [],
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeOrdered($query)
    {
        return $query->orderBy('start_date', 'desc')->orderBy('created_at', 'desc');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ── Relationships ─────────────────────────────────────────────────
    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function quote()
    {
        return $this->belongsTo(\InovCom\Devis\Models\Quote::class, 'quote_id');
    }

    public function offer()
    {
        return $this->belongsTo(\InovCom\Offres\Models\Offer::class, 'offer_id');
    }

    public function orders()
    {
        return $this->hasMany(MaintenanceOrder::class, 'contract_id');
    }

    // ── Business logic ────────────────────────────────────────────────

    /**
     * Compute SLA due_at for a new order based on contract response_time.
     */
    public function computeDueAt(\Carbon\Carbon $reportedAt): ?\Carbon\Carbon
    {
        if ($this->response_time) {
            return $reportedAt->copy()->addHours($this->response_time);
        }
        return null;
    }
}
