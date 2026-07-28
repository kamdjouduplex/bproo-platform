<?php

namespace InovCom\Projets\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use InovCom\Kernel\Traits\WorkflowStateMachine;

class Project extends TenantModel
{
    use Auditable, WorkflowStateMachine;

    protected $table = 'projects';

    protected $fillable = [
        'code', 'quote_id', 'client_id', 'title', 'status',
        'start_date', 'end_date', 'assigned_to', 'notes',
        'budget', 'actual_cost', 'progress_percent',
        'priority', 'project_type', 'contract_number', 'site_address',
        'completed_at', 'closed_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'budget'       => 'decimal:2',
        'actual_cost'  => 'decimal:2',
        'completed_at' => 'datetime',
        'closed_at'    => 'datetime',
    ];

    // ── WorkflowStateMachine ──────────────────────────────────────────────────
    public function allowedTransitions(): array
    {
        return [
            'planned'     => ['in_progress', 'on_hold'],
            'in_progress' => ['on_hold', 'completed'],
            'on_hold'     => ['in_progress', 'completed'],
            'completed'   => ['closed', 'in_progress'],
            'closed'      => [],
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeOrdered($query)
    {
        return $query->orderBy('start_date', 'desc')->orderBy('created_at', 'desc');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function quote()
    {
        return $this->belongsTo(\InovCom\Devis\Models\Quote::class, 'quote_id');
    }

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'assigned_to');
    }

    public function phases()
    {
        return $this->hasMany(ProjectPhase::class, 'project_id')->orderBy('position');
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class, 'project_id')->orderBy('position');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Recompute progress_percent from phases → tasks → site reports (in priority order).
     */
    public function recalculateProgress(): void
    {
        $phases = $this->phases()->get();
        if ($phases->isNotEmpty()) {
            $this->progress_percent = (int) $phases->avg('progress_percent');
            $this->saveQuietly();
            return;
        }

        $tasks = $this->tasks()->get();
        if ($tasks->isNotEmpty()) {
            $done = $tasks->where('status', 'done')->count();
            $this->progress_percent = (int) ($done / $tasks->count() * 100);
            $this->saveQuietly();
            return;
        }

        // Fall back to latest site report progress
        $this->recalculateProgressFromReports();
    }

    /**
     * Update progress_percent from the most recent validated/submitted site report.
     * Called by SiteReportForm after every save.
     */
    public function recalculateProgressFromReports(): void
    {
        if (!class_exists(\InovCom\Suivi\Models\SiteReport::class)) {
            return;
        }

        $latest = \InovCom\Suivi\Models\SiteReport::on('tenant')
            ->where('project_id', $this->id)
            ->whereIn('status', ['validated', 'submitted'])
            ->orderBy('report_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->value('progress_percent');

        if ($latest !== null) {
            $this->progress_percent = (int) $latest;
            $this->saveQuietly();
        }
    }

    /**
     * Recompute actual_cost from all financially committed purchase orders.
     * Includes validated, ordered, partially_received and received — excludes drafts and cancelled.
     * Called by PurchaseOrderForm after every save.
     */
    public function recalculateActualCost(): void
    {
        if (!class_exists(\InovCom\Achats\Models\PurchaseOrder::class)) {
            return;
        }

        $cost = \InovCom\Achats\Models\PurchaseOrder::on('tenant')
            ->where('project_id', $this->id)
            ->whereIn('status', ['validated', 'ordered', 'partially_received', 'received'])
            ->sum('total_ht');

        $this->actual_cost = $cost;
        $this->saveQuietly();
    }
}
