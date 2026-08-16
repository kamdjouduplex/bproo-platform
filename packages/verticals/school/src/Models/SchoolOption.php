<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolOption extends TenantModel
{
    protected $table = 'school_options';

    protected $fillable = [
        'group_key',
        'value',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function forGroup(string $groupKey, bool $activeOnly = true)
    {
        return static::query()
            ->where('group_key', $groupKey)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }
}
