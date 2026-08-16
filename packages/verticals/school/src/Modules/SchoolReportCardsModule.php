<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolReportCardsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_report_cards.view', 'name' => 'Voir les bulletins', 'description' => 'Consulter bulletins et relevés'],
            ['key' => 'school_report_cards.print', 'name' => 'Imprimer les bulletins', 'description' => 'Aperçu, impression et PDF navigateur'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_report_cards');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
