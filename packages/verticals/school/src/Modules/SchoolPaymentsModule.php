<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SyncsSchoolModulePermissions;

class SchoolPaymentsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_payments.view', 'name' => 'Voir les paiements', 'description' => 'Consulter paiements et reçus'],
            ['key' => 'school_payments.manage', 'name' => 'Enregistrer des paiements', 'description' => 'Saisir paiements banque / école'],
            ['key' => 'school_payments.verify', 'name' => 'Vérifier les paiements banque', 'description' => 'Valider un reçu banque'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_payments');
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
