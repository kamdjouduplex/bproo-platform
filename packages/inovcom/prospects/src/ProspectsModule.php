<?php

namespace InovCom\Prospects;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class ProspectsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'prospects.view', 'name' => 'Prospects — voir', 'description' => 'Consulter la liste et le détail des prospects'],
            ['key' => 'prospects.create', 'name' => 'Prospects — créer', 'description' => 'Ajouter de nouveaux prospects'],
            ['key' => 'prospects.update', 'name' => 'Prospects — modifier', 'description' => 'Éditer, changer le statut, affecter un commercial'],
            ['key' => 'prospects.convert', 'name' => 'Prospects — convertir en client', 'description' => 'Transformer un prospect qualifié en client (module Clients)'],
            ['key' => 'prospects.delete', 'name' => 'Prospects — supprimer', 'description' => 'Supprimer un prospect non converti'],
            ['key' => 'prospects.activities', 'name' => 'Prospects — activités', 'description' => 'Ajouter des activités sur un prospect'],
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
    }

    public function uninstall(object $tenant): void
    {
        //
    }
}
