<?php

namespace InovCom\InvoicePayments\Models;

use InovCom\Invoicing\Models\Invoice;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class InvoicePayment extends TenantModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'invoice_payments';

    protected $fillable = [
        'reference',
        'invoice_id',
        'amount',
        'withholding_total',
        'settled_amount',
        'status',
        'amount_paid_before',
        'balance_after',
        'payment_date',
        'payment_method',
        'external_reference',
        'notes',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'withholding_total' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'amount_paid_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'payment_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function withholdings()
    {
        return $this->hasMany(InvoicePaymentWithholding::class);
    }

    private static ?bool $withholdingsTableExists = null;

    public static function hasWithholdingsTable(): bool
    {
        if (self::$withholdingsTableExists === null) {
            self::$withholdingsTableExists = Schema::connection('tenant')->hasTable('invoice_payment_withholdings');
        }

        return self::$withholdingsTableExists;
    }

    public static function rememberWithholdingsTable(?bool $exists): void
    {
        self::$withholdingsTableExists = $exists;
    }

    /**
     * @return list<string>
     */
    public static function optionalWithholdingsRelation(): array
    {
        return self::hasWithholdingsTable() ? ['withholdings'] : [];
    }

    public function getWithholdingsAttribute()
    {
        if (!self::hasWithholdingsTable()) {
            return $this->relations['withholdings'] = $this->newCollection();
        }

        if ($this->relationLoaded('withholdings')) {
            return $this->relations['withholdings'];
        }

        return $this->getRelationshipFromMethod('withholdings');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_ACTIVE)
                ->orWhereNull('status');
        });
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isReceipt(): bool
    {
        return $this->settledAmount() > 0 && $this->isActive();
    }

    public function settledAmount(): float
    {
        if ($this->settled_amount !== null) {
            return round((float) $this->settled_amount, 2);
        }

        return round((float) $this->amount + (float) ($this->withholding_total ?? 0), 2);
    }

    public function withholdingTotal(): float
    {
        return round((float) ($this->withholding_total ?? 0), 2);
    }

    public function amountPaidAfter(): float
    {
        return round((float) ($this->amount_paid_before ?? 0) + $this->settledAmount(), 2);
    }

    public static function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Espèces',
            'check' => 'Chèque',
            'bank_transfer' => 'Virement bancaire',
            'mobile_money' => 'Mobile Money',
            'other' => 'Autre',
            default => $method,
        };
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_CANCELLED => 'Annulé',
            self::STATUS_ACTIVE => 'Actif',
            default => 'Actif',
        };
    }
}
