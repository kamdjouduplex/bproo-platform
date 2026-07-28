<?php

namespace InovCom\Users\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'tenant';
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'assigned_store_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assigned_store_id' => 'integer',
    ];

    /** Roles only — permissions are loaded on demand (avoids login 500 if RBAC tables are incomplete). */
    protected $with = ['roles'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withTimestamps();
    }

    public function hasPermission(string $key): bool
    {
        try {
            $this->loadMissing('roles.permissions');
        } catch (\Throwable) {
            return false;
        }

        foreach ($this->roles as $role) {
            if ($role->permissions->contains('key', $key)) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('name', 'admin');
    }

    public function canViewAllStores(): bool
    {
        return $this->isAdmin() || $this->hasPermission('stores.view_all');
    }
}
