<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBalanceTransaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'amount',
        'type',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPE_PAYMENT_CREDIT = 'payment_credit';
    public const TYPE_SUBSCRIPTION_APPLICATION = 'subscription_application';
    public const TYPE_PLAN_CHANGE_REFUND = 'plan_change_refund';
    public const TYPE_ADMIN_ADJUSTMENT = 'admin_adjustment';

    public static function types(): array
    {
        return [
            self::TYPE_PAYMENT_CREDIT => 'Versement (solde)',
            self::TYPE_SUBSCRIPTION_APPLICATION => 'Application à l\'abonnement',
            self::TYPE_PLAN_CHANGE_REFUND => 'Remboursement changement de plan',
            self::TYPE_ADMIN_ADJUSTMENT => 'Ajustement admin',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
