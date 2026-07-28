<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class ClientReminder extends TenantModel
{
    protected $fillable = [
        'client_id',
        'level',
        'channel',
        'amount_due',
        'due_date',
        'sent_at',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'level' => 'integer',
        'amount_due' => 'decimal:2',
        'due_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public const LEVELS = [
        1 => 'Niveau 1 — Rappel',
        2 => 'Niveau 2 — Mise en demeure',
        3 => 'Niveau 3 — Contentieux',
    ];

    public const CHANNELS = [
        'phone' => 'Téléphone',
        'sms' => 'SMS',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'visit' => 'Visite',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? ('Niveau ' . $this->level);
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }
}
