<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class OrderStageHistory extends TenantModel
{
    protected $table = 'order_stage_history';

    protected $fillable = [
        'order_id',
        'stage_id',
        'stage_name',
        'user_id',
        'moved_at',
        'note',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
