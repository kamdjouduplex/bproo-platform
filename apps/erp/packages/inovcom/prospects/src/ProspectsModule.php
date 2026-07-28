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
            ['key' => 'prospects.view', 'name' => 'Voir les prospects', 'description' => 'Consulter la liste et le détail des prospects'],
            ['key' => 'prospects.create', 'name' => 'Créer des prospects', 'description' => 'Ajouter de nouveaux prospects'],
            ['key' => 'prospects.update', 'name' => 'Modifier les prospects', 'description' => 'Éditer, changer le statut, ajouter une activité'],
            ['key' => 'prospects.convert', 'name' => 'Convertir en client', 'description' => 'Transformer un prospect qualifié en client'],
            ['key' => 'prospects.delete', 'name' => 'Supprimer des prospects', 'description' => 'Supprimer un prospect non converti'],
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
            $ids[] = $perm->id;
        }

        // Rôle admin : prêt immédiatement pour le menu (autres rôles via matrice).
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
