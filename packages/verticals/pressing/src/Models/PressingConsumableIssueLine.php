<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Items\Models\Item;
use InovCom\Kernel\TenantModel;

class PressingConsumableIssueLine extends TenantModel
{
    protected $table = 'pressing_consumable_issue_lines';

    protected $fillable = [
        'issue_id',
        'item_id',
        'quantity',
        'unit_label',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(PressingConsumableIssue::class, 'issue_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
