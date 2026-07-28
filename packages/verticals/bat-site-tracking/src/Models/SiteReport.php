<?php

namespace InovCom\Suivi\Models;

use InovCom\Kernel\TenantModel;

class SiteReport extends TenantModel
{
    protected $table = 'site_reports';

    protected $fillable = [
        'code',
        'project_id',
        'client_id',
        'assigned_to',
        'report_date',
        'weather',          // sunny | cloudy | rainy | windy | other
        'workers_count',
        'progress_percent', // 0–100
        'work_done',        // description of work accomplished
        'issues',           // incidents / blockers
        'next_steps',       // planned tasks for next day
        'status',           // draft | submitted | validated
        'pv_signed',        // boolean — client PV signed
        'pv_signed_at',
        'pv_client_name',   // client signatory name
        'notes',
    ];

    protected $casts = [
        'report_date'   => 'date',
        'pv_signed'     => 'boolean',
        'pv_signed_at'  => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOrdered($query)
    {
        return $query->orderBy('report_date', 'desc')->orderBy('created_at', 'desc');
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(\InovCom\Projets\Models\Project::class, 'project_id');
    }

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'assigned_to');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function weatherLabel(): string
    {
        return match ($this->weather) {
            'sunny'  => 'Ensoleillé',
            'cloudy' => 'Nuageux',
            'rainy'  => 'Pluvieux',
            'windy'  => 'Venteux',
            default  => 'Autre',
        };
    }

    public function weatherIcon(): string
    {
        return match ($this->weather) {
            'sunny'  => '☀️',
            'cloudy' => '⛅',
            'rainy'  => '🌧️',
            'windy'  => '💨',
            default  => '🌡️',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'submitted' => 'badge-info',
            'validated' => 'badge-success',
            default     => 'badge-ghost',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Soumis',
            'validated' => 'Validé',
            default     => 'Brouillon',
        };
    }
}
