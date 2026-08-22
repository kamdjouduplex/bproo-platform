<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolTimetableModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_timetable.view', 'name' => 'Voir l’emploi du temps', 'description' => 'Consulter les cours et la grille hebdomadaire'],
            ['key' => 'school_timetable.manage', 'name' => 'Gérer l’emploi du temps', 'description' => 'Attribuer les cours et placer les créneaux'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_timetable');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
