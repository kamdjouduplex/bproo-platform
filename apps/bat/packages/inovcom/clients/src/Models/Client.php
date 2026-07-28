<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class Client extends TenantModel
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'type',
        'legal_form', 'industry',
        'tax_id', 'rccm',
        'segment', 'website',
        'payment_terms', 'currency', 'credit_limit',
        'email', 'phone', 'phone2',
        'address', 'city', 'postal_code', 'country',
        'notes',
        'is_active', 'metadata',
        'assigned_to', 'last_contact_at',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class, 'client_id')->orderByDesc('is_primary');
    }

    public function activities()
    {
        return $this->hasMany(\InovCom\Clients\Models\ClientActivity::class, 'client_id')
            ->orderByDesc('activity_at');
    }
}
