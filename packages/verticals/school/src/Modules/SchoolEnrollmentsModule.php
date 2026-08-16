<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolEnrollmentsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_enrollments.view', 'name' => 'Voir les inscriptions', 'description' => 'Consulter les inscriptions par année'],
            ['key' => 'school_enrollments.manage', 'name' => 'Gérer les inscriptions', 'description' => 'Inscrire / modifier les inscriptions'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_enrollments');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
