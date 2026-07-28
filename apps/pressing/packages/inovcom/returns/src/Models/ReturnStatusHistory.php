<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;

class ReturnStatusHistory extends TenantModel
{
    protected $table = 'return_status_history';

    protected $fillable = [
        'return_id',
        'from_status',
        'to_status',
        'note',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    public function performer()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'performed_by');
    }
}
