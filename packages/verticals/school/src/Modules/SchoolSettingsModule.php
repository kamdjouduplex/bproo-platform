<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SchoolOptionCatalog;
use School\Support\SyncsSchoolModulePermissions;

class SchoolSettingsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_settings.view', 'name' => 'Voir le paramétrage école', 'description' => 'Consulter les listes configurables'],
            ['key' => 'school_settings.manage', 'name' => 'Gérer le paramétrage école', 'description' => 'Configurer sections, genres, statuts…'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_settings');

        try {
            SchoolOptionCatalog::seedDefaults();
        } catch (\Throwable) {
            // Table may not exist yet on first install before migrations finish.
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
