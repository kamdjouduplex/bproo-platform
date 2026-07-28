<?php

namespace InovCom\Projets\Models;

use InovCom\Kernel\TenantModel;

class ProjectMember extends TenantModel
{
    protected $table = 'project_members';

    protected $fillable = [
        'project_id', 'user_id', 'role',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'user_id');
    }
}
