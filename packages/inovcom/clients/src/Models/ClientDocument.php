<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class ClientDocument extends TenantModel
{
    protected $fillable = [
        'client_id',
        'type',
        'label',
        'path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public const TYPES = [
        'contract' => 'Contrat',
        'rccm' => 'RCCM',
        'tax_cert' => 'Attestation fiscale',
        'id_card' => 'Pièce d\'identité',
        'other' => 'Autre',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Autre';
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }
}
