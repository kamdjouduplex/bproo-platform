<?php

namespace InovCom\Projets\Models;

use InovCom\Kernel\TenantModel;

class ProjectPhase extends TenantModel
{
    protected $table = 'project_phases';

    protected $fillable = [
        'project_id', 'title', 'description', 'position', 'status',
        'planned_start', 'planned_end', 'actual_start', 'actual_end',
        'budget', 'progress_percent',
    ];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end'   => 'date',
        'actual_start'  => 'date',
        'actual_end'    => 'date',
        'budget'        => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(ProjectTask::class, 'phase_id')->orderBy('position');
    }
}
