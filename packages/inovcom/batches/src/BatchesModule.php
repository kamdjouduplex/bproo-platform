<?php

namespace InovCom\Batches;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class BatchesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'batches.view', 'name' => 'Voir les lots', 'description' => 'Consulter lots et dates de péremption'],
            ['key' => 'batches.create', 'name' => 'Créer / réceptionner lots', 'description' => 'Enregistrer des lots à la réception'],
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
        // Data kept for audit; optional cleanup
    }
}
