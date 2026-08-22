<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolDocumentsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_documents.view', 'name' => 'Voir les pièces', 'description' => 'Consulter les documents du dossier élève'],
            ['key' => 'school_documents.manage', 'name' => 'Gérer les pièces', 'description' => 'Ajouter / retirer les pièces du dossier'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_documents');
    }

    public function uninstall(object $tenant): void
    {
        // Keep files
    }
}
