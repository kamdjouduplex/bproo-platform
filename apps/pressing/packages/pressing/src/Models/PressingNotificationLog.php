<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingNotificationLog extends TenantModel
{
    protected $table = 'pressing_notification_logs';

    protected $fillable = [
        'event',
        'channel',
        'status',
        'order_id',
        'user_id',
        'recipient',
        'message',
        'error',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
