<?php

namespace InovCom\InvoicePayments\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class InvoicePaymentAttachment extends TenantModel
{
    protected $table = 'invoice_payment_attachments';

    protected $fillable = [
        'invoice_payment_id',
        'label',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function payment()
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $name = strtolower((string) $this->original_name);

        return str_ends_with($name, '.jpg')
            || str_ends_with($name, '.jpeg')
            || str_ends_with($name, '.png')
            || str_ends_with($name, '.webp')
            || str_ends_with($name, '.gif');
    }

    public function isPdf(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        $name = strtolower((string) $this->original_name);

        return $mime === 'application/pdf' || str_ends_with($name, '.pdf');
    }
}
