<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolTeachersModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_teachers.view', 'name' => 'Voir les enseignants', 'description' => 'Consulter les profils enseignants'],
            ['key' => 'school_teachers.manage', 'name' => 'Gérer les enseignants', 'description' => 'Créer / modifier les enseignants'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_teachers');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
