<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantPayment extends Model
{
    protected $table = 'tenant_payments';

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'amount',
        'currency',
        'paid_at',
        'method',
        'reference',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CHECK = 'check';
    public const METHOD_OTHER = 'other';

    public static function methods(): array
    {
        return [
            self::METHOD_CASH => 'Espèces',
            self::METHOD_BANK_TRANSFER => 'Virement',
            self::METHOD_CHECK => 'Chèque',
            self::METHOD_OTHER => 'Autre',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
