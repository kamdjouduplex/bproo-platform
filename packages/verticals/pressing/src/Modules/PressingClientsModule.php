<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class PressingClientsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pressing_clients.view', 'name' => 'Voir les clients pressing', 'description' => 'Accès liste et détail clients'],
            ['key' => 'pressing_clients.create', 'name' => 'Créer des clients pressing', 'description' => 'Créer de nouveaux clients'],
            ['key' => 'pressing_clients.update', 'name' => 'Modifier les clients pressing', 'description' => 'Modifier les clients'],
            ['key' => 'pressing_clients.delete', 'name' => 'Supprimer des clients pressing', 'description' => 'Supprimer des clients'],
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
        // Keep data
    }
}
