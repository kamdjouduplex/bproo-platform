<?php

namespace InovCom\Returns\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use InovCom\Kernel\TenantModel;
use InovCom\Returns\Enums\ResolutionType;
use InovCom\Returns\Enums\ReturnStatus;
use InovCom\Returns\Enums\ReturnType;

/**
 * En-tête d'une demande de retour client (table `returns`).
 */
class ReturnRequest extends TenantModel
{
    use SoftDeletes;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'client_id',
        'source_type',
        'source_id',
        'source_number',
        'status',
        'type',
        'resolution_type',
        'return_date',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'reason_id',
        'notes',
        'store_id',
        'created_by',
        'approved_by',
        'approved_at',
        'received_by',
        'received_at',
        'inspected_by',
        'inspected_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => ReturnStatus::class,
        'type' => ReturnType::class,
        'resolution_type' => ResolutionType::class,
        'return_date' => 'date',
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'inspected_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id')->orderBy('id');
    }

    public function reason()
    {
        return $this->belongsTo(ReturnReason::class, 'reason_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(ReturnStatusHistory::class, 'return_id')->orderByDesc('performed_at')->orderByDesc('id');
    }

    public function comments()
    {
        return $this->hasMany(ReturnComment::class, 'return_id')->orderByDesc('id');
    }

    public function attachments()
    {
        return $this->hasMany(ReturnAttachment::class, 'return_id')->orderByDesc('id');
    }

    public function creditNote()
    {
        return $this->hasOne(CreditNote::class, 'return_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'return_id');
    }

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function creator()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'created_by');
    }

    public function sourceInvoice()
    {
        if ($this->source_type !== 'invoice' || ! $this->source_id) {
            return null;
        }

        return \InovCom\Invoicing\Models\Invoice::on('tenant')->find($this->source_id);
    }

    public function isEditable(): bool
    {
        return $this->status instanceof ReturnStatus && $this->status->isEditable();
    }

    public function canBeInspected(): bool
    {
        return $this->status === ReturnStatus::Received;
    }
}
