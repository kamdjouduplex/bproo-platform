<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolIdCardsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_id_cards.view', 'name' => 'Voir les cartes ID', 'description' => 'Consulter / prévisualiser les cartes'],
            ['key' => 'school_id_cards.manage', 'name' => 'Générer les cartes ID', 'description' => 'Générer et imprimer cartes (unitaire / lot)'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_id_cards');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
