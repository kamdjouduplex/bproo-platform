<?php

namespace School\Support;

use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

trait SyncsSchoolModulePermissions
{
    /**
     * @param  array<int, array{key: string, name: string, description?: string|null}>  $permissions
     */
    protected static function syncPermissions(array $permissions, string $keyPrefix): void
    {
        foreach ($permissions as $p) {
            Permission::on('tenant')->updateOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin) {
            $ids = Permission::on('tenant')->where('key', 'like', $keyPrefix.'.%')->pluck('id');
            $admin->permissions()->syncWithoutDetaching($ids);
        }

        SchoolRoleCatalog::sync();
    }
}
