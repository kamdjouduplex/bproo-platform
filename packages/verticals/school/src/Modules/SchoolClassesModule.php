<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolClassesModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_classes.view', 'name' => 'Voir les classes', 'description' => 'Consulter les classes / niveaux'],
            ['key' => 'school_classes.manage', 'name' => 'Gérer les classes', 'description' => 'Créer / modifier les classes'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_classes');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
