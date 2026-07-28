<?php

namespace InovCom\Invoicing\Models;

use InovCom\Kernel\TenantModel;

class DeliveryNoteLine extends TenantModel
{
    protected $fillable = [
        'delivery_note_id',
        'invoice_line_id',
        'quotation_line_id',
        'item_id',
        'item_name',
        'item_sku',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function invoiceLine()
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function quotationLine()
    {
        return $this->belongsTo(\InovCom\Quotations\Models\QuotationLine::class);
    }
}
