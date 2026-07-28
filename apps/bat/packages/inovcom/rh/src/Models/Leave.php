<?php

namespace InovCom\Rh\Models;

use InovCom\Kernel\TenantModel;

class Leave extends TenantModel
{
    protected $table = 'leaves';

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'days_count',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function typeLabel(): string
    {
        return match ($this->type) {
            'annual'    => 'Congé annuel',
            'sick'      => 'Arrêt maladie',
            'maternity' => 'Maternité',
            'paternity' => 'Paternité',
            'unpaid'    => 'Sans solde',
            default     => 'Autre',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'  => 'En attente',
            'approved' => 'Approuvé',
            'rejected' => 'Refusé',
            default    => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending'  => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default    => 'badge-secondary',
        };
    }

    public static function types(): array
    {
        return [
            'annual'    => 'Congé annuel',
            'sick'      => 'Arrêt maladie',
            'maternity' => 'Maternité',
            'paternity' => 'Paternité',
            'unpaid'    => 'Sans solde',
            'other'     => 'Autre',
        ];
    }
}
