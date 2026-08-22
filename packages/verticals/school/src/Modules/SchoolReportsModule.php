<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolReportsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_reports.view', 'name' => 'Voir les rapports', 'description' => 'Consulter et imprimer les rapports école'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_reports');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
