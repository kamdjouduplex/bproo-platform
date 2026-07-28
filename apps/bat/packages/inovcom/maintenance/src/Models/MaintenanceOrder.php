<?php

namespace InovCom\Maintenance\Models;

use Carbon\Carbon;
use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use InovCom\Kernel\Traits\WorkflowStateMachine;

class MaintenanceOrder extends TenantModel
{
    use Auditable, WorkflowStateMachine;

    protected $table = 'maintenance_orders';

    protected $fillable = [
        'code', 'contract_id', 'client_id', 'title', 'description',
        'type', 'priority', 'status',
        'site_address', 'reported_by', 'reported_at', 'due_at',
        'assigned_to',
        'assigned_at', 'started_at', 'done_at', 'closed_at', 'cancelled_at',
    ];

    protected $casts = [
        'reported_at'  => 'datetime',
        'due_at'       => 'datetime',
        'assigned_at'  => 'datetime',
        'started_at'   => 'datetime',
        'done_at'      => 'datetime',
        'closed_at'    => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ── WorkflowStateMachine ──────────────────────────────────────────
    public function allowedTransitions(): array
    {
        return [
            'open'        => ['assigned', 'cancelled'],
            'assigned'    => ['in_progress', 'cancelled'],
            'in_progress' => ['done', 'cancelled'],
            'done'        => ['closed', 'in_progress'],   // reopen if client disputes
            'closed'      => [],
            'cancelled'   => [],
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────
    public function scopeOrdered($query)
    {
        return $query->orderBy('reported_at', 'desc');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'assigned', 'in_progress']);
    }

    public function scopeOverdueSla($query)
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', ['done', 'closed', 'cancelled']);
    }

    // ── Relationships ─────────────────────────────────────────────────
    public function contract()
    {
        return $this->belongsTo(MaintenanceContract::class, 'contract_id');
    }

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'assigned_to');
    }

    public function interventions()
    {
        return $this->hasMany(Intervention::class, 'maintenance_order_id')->orderBy('scheduled_at');
    }

    // ── Business logic ────────────────────────────────────────────────

    /**
     * Is this order breaching its SLA deadline?
     */
    public function isSlaBreached(): bool
    {
        return $this->due_at && now()->isAfter($this->due_at)
            && !in_array($this->status, ['done', 'closed', 'cancelled']);
    }

    /**
     * Hours remaining until SLA deadline (negative = overdue).
     */
    public function slaHoursRemaining(): ?float
    {
        if (!$this->due_at) {
            return null;
        }
        return round(now()->diffInMinutes($this->due_at, false) / 60, 1);
    }

    /**
     * Priority CSS badge class for views.
     */
    public function priorityBadgeClass(): string
    {
        return match ($this->priority) {
            'critical' => 'badge-error',
            'high'     => 'badge-warning',
            'normal'   => 'badge-info',
            default    => 'badge-ghost',
        };
    }
}
