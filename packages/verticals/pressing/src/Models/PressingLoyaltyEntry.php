<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingLoyaltyEntry extends TenantModel
{
    protected $table = 'pressing_loyalty_entries';

    public const TYPE_EARN = 'earn';

    public const TYPE_REDEEM = 'redeem';

    public const TYPE_ADJUST = 'adjust';

    protected $fillable = [
        'client_id',
        'order_id',
        'type',
        'points',
        'balance_after',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(PressingClient::class, 'client_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
