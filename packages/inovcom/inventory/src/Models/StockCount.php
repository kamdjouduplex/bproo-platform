<?php

namespace InovCom\Inventory\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class StockCount extends TenantModel
{
    protected $fillable = [
        'reference',
        'title',
        'description',
        'status',
        'started_at',
        'completed_at',
        'started_by',
        'completed_by',
        'allow_operations',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'allow_operations' => 'boolean',
        'metadata' => 'array',
    ];

    public function lines()
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function adjustments()
    {
        return $this->hasMany(Adjustment::class);
    }

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getTotalExpectedQuantityAttribute(): float
    {
        return $this->lines()->sum('expected_quantity');
    }

    public function getTotalCountedQuantityAttribute(): float
    {
        return $this->lines()->sum('counted_quantity');
    }

    public function getTotalDifferenceAttribute(): float
    {
        return $this->lines()->sum('difference');
    }

    public function getTotalValueDifferenceAttribute(): float
    {
        return $this->lines()->sum('value_difference');
    }

    public function getProgressPercentageAttribute(): float
    {
        $total = $this->lines()->count();
        if ($total === 0) {
            return 0;
        }
        $counted = $this->lines()->whereNotNull('counted_quantity')->count();
        return ($counted / $total) * 100;
    }
}
