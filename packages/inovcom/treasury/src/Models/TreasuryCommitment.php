<?php

namespace InovCom\Treasury\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Providers\Models\Provider;
use InovCom\Users\Models\User;

class TreasuryCommitment extends TenantModel
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const FREQ_ONCE = 'once';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_YEARLY = 'yearly';

    protected $table = 'treasury_commitments';

    protected $fillable = [
        'label',
        'category',
        'amount',
        'due_date',
        'frequency',
        'account_code',
        'provider_id',
        'beneficiary',
        'comment',
        'status',
        'priority',
        'alert_days',
        'paid_dates',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_dates' => 'array',
        'alert_days' => 'integer',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRecurring(): bool
    {
        return $this->frequency !== self::FREQ_ONCE;
    }

    public function paidDates(): array
    {
        return array_values(array_filter(array_map('strval', $this->paid_dates ?? [])));
    }

    public function markDatePaid(string $date): void
    {
        $dates = $this->paidDates();
        if (!in_array($date, $dates, true)) {
            $dates[] = $date;
        }
        $this->paid_dates = $dates;
        if (!$this->isRecurring()) {
            $this->status = self::STATUS_PAID;
        }
        $this->save();
    }

    public static function frequencyLabel(string $frequency): string
    {
        return match ($frequency) {
            self::FREQ_WEEKLY => 'Hebdomadaire',
            self::FREQ_MONTHLY => 'Mensuelle',
            self::FREQ_YEARLY => 'Annuelle',
            default => 'Unique',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'Payé',
            self::STATUS_CANCELLED => 'Annulé',
            default => 'Planifié',
        };
    }

    public static function categoryOptions(): array
    {
        return [
            'loyer' => 'Loyer',
            'internet' => 'Internet',
            'electricite' => 'Électricité',
            'dette' => 'Dette / emprunt',
            'salaire' => 'Salaire',
            'fournisseur' => 'Fournisseur',
            'abonnement' => 'Abonnement',
            'impot' => 'Impôt / taxe',
            'autre' => 'Autre',
        ];
    }
}
