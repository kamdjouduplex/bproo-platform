<?php

namespace InovCom\Tickets;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class TicketsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'tickets.view', 'name' => 'Voir les tickets', 'description' => 'Consulter la liste et le détail des tickets'],
            ['key' => 'tickets.create', 'name' => 'Créer des tickets', 'description' => 'Ouvrir un nouveau ticket'],
            ['key' => 'tickets.update', 'name' => 'Modifier les tickets', 'description' => 'Commenter, changer statut et assignation'],
            ['key' => 'tickets.close', 'name' => 'Clôturer les tickets', 'description' => 'Clôturer et réouvrir des tickets'],
            ['key' => 'tickets.delete', 'name' => 'Supprimer des tickets', 'description' => 'Supprimer des tickets ouverts sans activité'],
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
