<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InovCom\Kernel\TenantModel;

class WorkflowStage extends TenantModel
{
    protected $table = 'workflow_stages';

    protected $fillable = [
        'agence_id',
        'name',
        'color',
        'icon',
        'sort_order',
        'estimated_minutes',
        'is_active',
        'is_final',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'estimated_minutes' => 'integer',
        'is_active' => 'boolean',
        'is_final' => 'boolean',
    ];

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PressingOrder::class, 'current_stage_id');
    }
}
