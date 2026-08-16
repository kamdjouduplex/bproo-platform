<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolExamsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_exams.view', 'name' => 'Voir les examens', 'description' => 'Consulter examens et notes'],
            ['key' => 'school_exams.manage', 'name' => 'Gérer les examens', 'description' => 'Créer / modifier les épreuves'],
            ['key' => 'school_exams.marks', 'name' => 'Saisir les notes', 'description' => 'Entrer et valider les notes'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_exams');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
