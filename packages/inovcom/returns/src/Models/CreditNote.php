<?php

namespace InovCom\Returns\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use InovCom\Kernel\TenantModel;
use InovCom\Returns\Enums\CreditNoteStatus;

class CreditNote extends TenantModel
{
    use SoftDeletes;

    protected $table = 'credit_notes';

    protected $fillable = [
        'credit_note_number',
        'client_id',
        'return_id',
        'invoice_id',
        'status',
        'issue_date',
        'subtotal',
        'tax_amount',
        'total',
        'used_amount',
        'remaining_amount',
        'reason',
        'store_id',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'status' => CreditNoteStatus::class,
        'issue_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'used_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'validated_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class, 'credit_note_id')->orderBy('id');
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'credit_note_id');
    }

    public function invoice()
    {
        if (! $this->invoice_id) {
            return null;
        }

        return \InovCom\Invoicing\Models\Invoice::on('tenant')->find($this->invoice_id);
    }

    public function isUsable(): bool
    {
        return $this->status instanceof CreditNoteStatus
            && $this->status->isUsable()
            && (float) $this->remaining_amount > 0.01;
    }
}
