<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolPilotageModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_pilotage.view', 'name' => 'Voir le pilotage', 'description' => 'Consulter le cockpit de direction (effectifs, recouvrement, alertes)'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_pilotage');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
