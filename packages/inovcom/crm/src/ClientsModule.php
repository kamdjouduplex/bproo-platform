<?php

namespace InovCom\Clients;

use InovCom\Clients\Models\Segment;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class ClientsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'clients.view', 'name' => 'Voir les clients', 'description' => 'Accès liste et détail clients'],
            ['key' => 'clients.create', 'name' => 'Créer des clients', 'description' => 'Créer de nouveaux clients'],
            ['key' => 'clients.update', 'name' => 'Modifier les clients', 'description' => 'Modifier les clients existants'],
            ['key' => 'clients.delete', 'name' => 'Supprimer des clients', 'description' => 'Supprimer des clients'],
            ['key' => 'segments.manage', 'name' => 'Gérer les segments', 'description' => 'Créer, modifier, supprimer segments'],
        ];
    }

    public function install(object $tenant): void
    {
        // Register permissions
        foreach (self::defaultPermissions() as $p) {
            Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }

        // Create default segment
        Segment::firstOrCreate(
            ['code' => 'default'],
            [
                'name' => 'Général',
                'description' => 'Segment par défaut',
                'is_active' => true,
            ]
        );
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup. We keep clients data for now.
    }
}
