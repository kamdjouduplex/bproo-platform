<?php

namespace InovCom\Users\Models;

use InovCom\Kernel\TenantModel;

class Permission extends TenantModel
{
    protected $fillable = ['key', 'name', 'description'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role', 'permission_id', 'role_id')
            ->withTimestamps();
    }
}
