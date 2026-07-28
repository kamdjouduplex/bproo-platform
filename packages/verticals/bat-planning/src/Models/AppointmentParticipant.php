<?php

namespace InovCom\Planning\Models;

use InovCom\Kernel\TenantModel;

class AppointmentParticipant extends TenantModel
{
    protected $table = 'planning_participants';

    protected $fillable = [
        'appointment_id', 'user_id', 'name', 'email', 'phone', 'is_external',
    ];

    protected $casts = [
        'is_external' => 'boolean',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function user()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'user_id');
    }

    public function displayName(): string
    {
        return $this->name ?? $this->user?->name ?? '—';
    }
}
