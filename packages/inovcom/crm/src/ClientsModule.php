<?php

namespace InovCom\Clients;

use InovCom\Clients\Models\Segment;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class ClientsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'clients.view', 'name' => 'Clients — voir', 'description' => 'Accès liste, fiche et vue 360° clients'],
            ['key' => 'clients.create', 'name' => 'Clients — créer', 'description' => 'Créer de nouveaux clients'],
            ['key' => 'clients.update', 'name' => 'Clients — modifier', 'description' => 'Modifier fiche, contacts, adresses, notes'],
            ['key' => 'clients.delete', 'name' => 'Clients — supprimer', 'description' => 'Supprimer ou désactiver des clients'],
            ['key' => 'clients.credit', 'name' => 'Clients — crédit', 'description' => 'Gérer les limites de crédit'],
            ['key' => 'clients.export', 'name' => 'Clients — exporter', 'description' => 'Exporter la liste clients'],
            ['key' => 'segments.manage', 'name' => 'Clients — segments', 'description' => 'Créer, modifier, supprimer les segments'],
        ];
    }

    public function install(object $tenant): void
    {
        $ids = [];
        foreach (self::defaultPermissions() as $p) {
            $perm = Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
            $perm->fill([
                'name' => $p['name'],
                'description' => $p['description'] ?? null,
            ])->save();
            $ids[] = $perm->id;
        }

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin && $ids !== []) {
            $admin->permissions()->syncWithoutDetaching($ids);
        }

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
        // Keep clients data.
    }
}
