<?php

namespace InovCom\Caisse\Models;

use InovCom\Kernel\TenantModel;

class CaisseSession extends TenantModel
{
    protected $table = 'caisse_sessions';

    protected $fillable = [
        'session_number',
        'opened_by',
        'opened_at',
        'opening_amount',
        'status',
        'closed_by',
        'closed_at',
        'closing_amount_expected',
        'closing_amount_counted',
        'close_note',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'closing_amount_expected' => 'decimal:2',
        'closing_amount_counted' => 'decimal:2',
    ];

    public function entries()
    {
        return $this->hasMany(CaisseEntry::class, 'caisse_session_id');
    }

    public function opener()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
