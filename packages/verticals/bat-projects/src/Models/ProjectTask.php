<?php

namespace InovCom\Projets\Models;

use InovCom\Kernel\TenantModel;

class ProjectTask extends TenantModel
{
    protected $table = 'project_tasks';

    protected $fillable = [
        'project_id', 'phase_id', 'assigned_to', 'title', 'description',
        'status', 'priority', 'position',
        'planned_start', 'planned_end', 'actual_start', 'actual_end',
        'estimated_hours', 'actual_hours',
    ];

    protected $casts = [
        'planned_start'   => 'date',
        'planned_end'     => 'date',
        'actual_start'    => 'date',
        'actual_end'      => 'date',
        'estimated_hours' => 'decimal:2',
        'actual_hours'    => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function phase()
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'assigned_to');
    }
}
