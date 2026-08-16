<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolSubjectsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_subjects.view', 'name' => 'Voir les matières', 'description' => 'Consulter le référentiel des matières'],
            ['key' => 'school_subjects.manage', 'name' => 'Gérer les matières', 'description' => 'Créer / modifier les matières'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_subjects');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
