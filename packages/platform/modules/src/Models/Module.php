<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'key',
        'label',
        'description',
        'route_name',
        'lifecycle_handler',
        'enabled_by_default',
        'version',
        'installed_version',
        'compatibility',
        'package_name',
    ];

    protected $casts = [
        'enabled_by_default' => 'boolean',
        'compatibility' => 'array',
    ];

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_modules')
            ->withPivot(['enabled'])
            ->withTimestamps();
    }
}
