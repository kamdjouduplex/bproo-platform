<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class PressingDeliveriesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pressing_deliveries.view', 'name' => 'Voir les livraisons', 'description' => 'Accès liste livraisons'],
            ['key' => 'pressing_deliveries.create', 'name' => 'Planifier des livraisons', 'description' => 'Créer une livraison'],
            ['key' => 'pressing_deliveries.update', 'name' => 'Mettre à jour les livraisons', 'description' => 'Changer statut, confirmer livraison'],
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
