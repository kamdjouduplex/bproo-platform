<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class ClientNote extends TenantModel
{
    protected $fillable = [
        'client_id',
        'body',
        'type',
        'author_id',
    ];

    public const TYPES = [
        'note' => 'Note',
        'call' => 'Appel',
        'meeting' => 'Rendez-vous',
        'reminder' => 'Relance',
        'system' => 'Système',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Note';
    }
}
