<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolStudentsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_students.view', 'name' => 'Voir les élèves', 'description' => 'Consulter les profils élèves'],
            ['key' => 'school_students.manage', 'name' => 'Gérer les élèves', 'description' => 'Créer / modifier les profils élèves'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_students');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
