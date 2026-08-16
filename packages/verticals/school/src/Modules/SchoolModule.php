<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;
use School\Support\SchoolRoleCatalog;
use School\Support\SyncsSchoolModulePermissions;

/**
 * Tableau de bord École — point d’entrée optionnel (pas le dump métier).
 */
class SchoolModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school.view', 'name' => 'École — tableau de bord', 'description' => 'Accéder au tableau de bord School'],
            ['key' => 'school.manage', 'name' => 'École — administrer', 'description' => 'Superviser la configuration School'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school');
        SchoolRoleCatalog::sync();

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin) {
            $ids = Permission::on('tenant')->whereIn('key', ['school.view', 'school.manage'])->pluck('id');
            $admin->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep roles/data
    }
}
