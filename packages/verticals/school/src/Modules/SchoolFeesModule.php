<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolFeesModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_fees.view', 'name' => 'Voir les structures de frais', 'description' => 'Consulter les barèmes de frais'],
            ['key' => 'school_fees.manage', 'name' => 'Gérer les structures de frais', 'description' => 'Créer et modifier les barèmes'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_fees');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
