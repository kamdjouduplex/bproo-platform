<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingConsumableIssue extends TenantModel
{
    protected $table = 'pressing_consumable_issues';

    public const TYPE_ATELIER = 'atelier';

    public const TYPE_LIVRAISON = 'livraison';

    public const PURPOSES = [
        'lavage' => 'Lavage',
        'sechage' => 'Séchage',
        'repassage' => 'Repassage',
        'finition' => 'Finition / emballage atelier',
        'nettoyage' => 'Nettoyage matériel',
        'livraison' => 'Remise client',
        'autre' => 'Autre',
    ];

    protected $fillable = [
        'number',
        'type',
        'order_id',
        'delivery_id',
        'taken_by',
        'issued_by',
        'purpose',
        'work_context',
        'pieces_processed',
        'notes',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'pieces_processed' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(PressingDelivery::class, 'delivery_id');
    }

    public function taker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PressingConsumableIssueLine::class, 'issue_id');
    }

    public function purposeLabel(): string
    {
        $label = self::PURPOSES[$this->purpose] ?? ($this->purpose ?: '—');

        return $label === '—' ? $label : __($label);
    }
}
