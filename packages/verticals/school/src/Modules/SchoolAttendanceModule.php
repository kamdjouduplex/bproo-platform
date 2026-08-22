<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolAttendanceModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_attendance.view', 'name' => 'Voir les présences', 'description' => 'Consulter l’appel par cours et l’historique'],
            ['key' => 'school_attendance.manage', 'name' => 'Saisir les présences', 'description' => 'Enregistrer l’appel de chaque séance'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_attendance');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
