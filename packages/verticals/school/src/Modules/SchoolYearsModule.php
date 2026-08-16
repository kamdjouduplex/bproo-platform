<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolYearsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_years.view', 'name' => 'Voir les années académiques', 'description' => 'Consulter les années académiques'],
            ['key' => 'school_years.manage', 'name' => 'Gérer les années académiques', 'description' => 'Créer / modifier / clôturer les années'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_years');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
