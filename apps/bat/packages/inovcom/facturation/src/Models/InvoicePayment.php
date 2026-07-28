<?php

namespace InovCom\Facturation\Models;

use InovCom\Facturation\Support\PaymentMethodLabels;
use InovCom\Kernel\TenantModel;

class InvoicePayment extends TenantModel
{
    protected $table = 'invoice_payments';

    protected $fillable = [
        'invoice_id', 'amount', 'payment_date',
        'payment_method', 'reference', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function recordedByUser()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'recorded_by');
    }

    /**
     * Unique receipt reference for archiving (e.g. REC-FAC00001-02).
     */
    public function receiptCode(): string
    {
        $invoice = $this->relationLoaded('invoice')
            ? $this->invoice
            : $this->invoice()->first(['id', 'code']);

        $sequence = static::on('tenant')
            ->where('invoice_id', $this->invoice_id)
            ->where(function ($q) {
                $q->where('payment_date', '<', $this->payment_date)
                    ->orWhere(function ($q2) {
                        $q2->where('payment_date', $this->payment_date)
                            ->where('id', '<=', $this->id);
                    });
            })
            ->count();

        return 'REC-' . ($invoice?->code ?? 'FAC') . '-' . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    public function paymentMethodLabel(): string
    {
        return PaymentMethodLabels::label($this->payment_method);
    }
}
