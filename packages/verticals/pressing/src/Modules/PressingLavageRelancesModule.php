<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class PressingLavageRelancesModule implements ModuleLifecycle
{
    /**
     * @return array<int, array{key: string, name: string, description?: ?string}>
     */
    public static function defaultPermissions(): array
    {
        return [
            [
                'key' => 'pressing_lavage_relances.view',
                'name' => 'Voir relances dépôt lavage',
                'description' => 'Accès à l’écran “clients sans dépôt lavage”',
            ],
            [
                'key' => 'pressing_lavage_relances.print',
                'name' => 'Imprimer relances dépôt lavage',
                'description' => 'Impression de la liste des clients à relancer',
            ],
            [
                'key' => 'pressing_lavage_relances.relaunch',
                'name' => 'Relancer clients dépôt lavage',
                'description' => 'Créer des tickets de relance pour les clients concernés',
            ],
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

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin) {
            $ids = Permission::on('tenant')
                ->where('key', 'like', 'pressing_lavage_relances.%')
                ->pluck('id');

            $admin->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}

