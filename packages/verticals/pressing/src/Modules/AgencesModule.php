<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class AgencesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'agences.view', 'name' => 'Voir les agences', 'description' => 'Accès liste et détail agences'],
            ['key' => 'agences.create', 'name' => 'Créer des agences', 'description' => 'Créer de nouvelles agences'],
            ['key' => 'agences.update', 'name' => 'Modifier les agences', 'description' => 'Modifier les agences'],
            ['key' => 'agences.delete', 'name' => 'Supprimer des agences', 'description' => 'Supprimer des agences'],
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
