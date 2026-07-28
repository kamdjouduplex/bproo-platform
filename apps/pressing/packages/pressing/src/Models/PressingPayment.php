<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingPayment extends TenantModel
{
    protected $table = 'pressing_payments';

    public const METHODS = [
        'cash' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'card' => 'Carte bancaire',
        'transfer' => 'Virement',
    ];

    protected $fillable = [
        'order_id',
        'agence_id',
        'method',
        'amount',
        'reference',
        'received_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
