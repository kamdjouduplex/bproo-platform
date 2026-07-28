<?php

namespace InovCom\Dms\Models;

use InovCom\Kernel\TenantModel;

class DocumentAttachment extends TenantModel
{
    protected $table = 'document_attachments';

    protected $fillable = [
        'document_id', 'attachable_type', 'attachable_id',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
