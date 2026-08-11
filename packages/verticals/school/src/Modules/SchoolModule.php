<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

/**
 * Vertical entry for Bproo School.
 * Seeds default permissions/roles for school tenants.
 */
class SchoolModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school.view', 'name' => 'École — hub', 'description' => 'Accéder au hub School'],
            ['key' => 'school.manage', 'name' => 'École — administrer', 'description' => 'Configurer modules School'],
        ];
    }

    public static function defaultRoles(): array
    {
        return [
            [
                'name' => 'directeur',
                'description' => 'Directeur — configuration et publication des résultats',
                'permissions' => [
                    'school.view', 'school.manage',
                    'users.view',
                    'configuration.view',
                ],
            ],
            [
                'name' => 'enseignant',
                'description' => 'Enseignant — saisie des notes',
                'permissions' => [
                    'school.view',
                ],
            ],
            [
                'name' => 'caissier',
                'description' => 'Caissier — paiements et reçus',
                'permissions' => [
                    'school.view',
                ],
            ],
        ];
    }

    public function install(object $tenant): void
    {
        $ids = [];
        foreach (self::defaultPermissions() as $p) {
            $perm = Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
            $perm->fill([
                'name' => $p['name'],
                'description' => $p['description'] ?? null,
            ])->save();
            $ids[] = $perm->id;
        }

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin && $ids !== []) {
            $admin->permissions()->syncWithoutDetaching($ids);
        }

        foreach (self::defaultRoles() as $roleDef) {
            $role = Role::on('tenant')->firstOrCreate(
                ['name' => $roleDef['name']],
                ['description' => $roleDef['description'] ?? null]
            );

            $permIds = Permission::on('tenant')
                ->whereIn('key', $roleDef['permissions'])
                ->pluck('id')
                ->all();

            if ($permIds !== []) {
                $role->permissions()->syncWithoutDetaching($permIds);
            }
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep roles/data
    }
}

