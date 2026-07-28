<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class Contact extends TenantModel
{
    protected $fillable = [
        'client_id',
        'civility',
        'first_name',
        'last_name',
        'position',
        'role',
        'email',
        'phone',
        'mobile',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const ROLES = [
        'principal' => 'Contact principal',
        'buyer' => 'Acheteur',
        'accountant' => 'Comptable',
        'director' => 'Directeur',
        'technician' => 'Technicien',
        'other' => 'Autre',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->civility ? $this->civility . ' ' : '') . $this->first_name . ' ' . $this->last_name);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? 'Autre';
    }
}
