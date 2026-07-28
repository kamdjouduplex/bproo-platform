<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;

class ReturnAttachment extends TenantModel
{
    protected $table = 'return_attachments';

    protected $fillable = [
        'return_id',
        'type',
        'label',
        'path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
