<?php

namespace InovCom\Quotations;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class QuotationsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'quotations.view', 'name' => 'Voir les devis', 'description' => 'Accès liste et détail des devis'],
            ['key' => 'quotations.create', 'name' => 'Créer des devis', 'description' => 'Créer de nouveaux devis'],
            ['key' => 'quotations.update', 'name' => 'Modifier les devis', 'description' => 'Modifier les devis existants'],
            ['key' => 'quotations.delete', 'name' => 'Supprimer des devis', 'description' => 'Supprimer des devis brouillon'],
            ['key' => 'quotations.validate', 'name' => 'Gérer le workflow des devis', 'description' => 'Envoyer, accepter, rejeter ou suspendre des devis'],
        ];
    }

    public function install(object $tenant): void
    {
        foreach (self::defaultPermissions() as $p) {
            Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        //
    }
}
