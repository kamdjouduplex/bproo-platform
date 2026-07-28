<?php

namespace InovCom\Planning\Models;

use Carbon\Carbon;
use InovCom\Kernel\TenantModel;

class Appointment extends TenantModel
{
    protected $table = 'planning_appointments';

    protected $fillable = [
        'code', 'title', 'description',
        'type', 'status',
        'start_at', 'end_at',
        'location', 'notes', 'report',
        'assigned_to', 'client_id', 'project_id', 'maintenance_order_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeUpcoming($query)
    {
        return $query->where('start_at', '>=', now())
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('start_at');
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth()->endOfDay();
        return $query->whereBetween('start_at', [$start, $end]);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'assigned_to');
    }

    public function participants()
    {
        return $this->hasMany(AppointmentParticipant::class, 'appointment_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            'visit_terrain'      => 'apt-type--visit',
            'reunion'            => 'apt-type--reunion',
            'maintenance'        => 'apt-type--maintenance',
            'project_milestone'  => 'apt-type--milestone',
            default              => 'apt-type--other',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'visit_terrain'      => 'Visite terrain',
            'reunion'            => 'Réunion',
            'maintenance'        => 'Maintenance',
            'project_milestone'  => 'Jalon projet',
            default              => 'Autre',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'confirmed' => 'badge-status--confirmed',
            'done'      => 'badge-status--done',
            'cancelled' => 'badge-status--cancelled',
            default     => 'badge-status--scheduled',
        };
    }

    public function durationMinutes(): int
    {
        return (int) $this->start_at->diffInMinutes($this->end_at);
    }
}
