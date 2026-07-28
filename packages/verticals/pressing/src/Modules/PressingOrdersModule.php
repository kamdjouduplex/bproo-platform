<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;
use Pressing\Models\ArticleType;

class PressingOrdersModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pressing_orders.view', 'name' => 'Voir les commandes pressing', 'description' => 'Accès liste et détail commandes'],
            ['key' => 'pressing_orders.create', 'name' => 'Créer des commandes pressing', 'description' => 'Réception / création de commandes'],
            ['key' => 'pressing_orders.sort', 'name' => 'Effectuer le tri', 'description' => 'Cataloguer les pièces après réception'],
            ['key' => 'pressing_orders.update', 'name' => 'Modifier les commandes pressing', 'description' => 'Modifier commandes et articles'],
            ['key' => 'pressing_orders.delete', 'name' => 'Supprimer des commandes pressing', 'description' => 'Annuler / supprimer commandes'],
            ['key' => 'pressing_orders.pay', 'name' => 'Encaisser paiements pressing', 'description' => 'Enregistrer des paiements'],
            ['key' => 'pressing_orders.request_credit', 'name' => 'Demander un crédit pressing', 'description' => 'Demander un crédit pour une commande non soldée'],
            ['key' => 'pressing_orders.validate_credit', 'name' => 'Valider un crédit pressing', 'description' => 'Approuver ou refuser un crédit client avant livraison'],
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
            $ids = Permission::on('tenant')->where('key', 'like', 'pressing_orders.%')->pluck('id');
            $admin->permissions()->syncWithoutDetaching($ids);
        }

        $defaults = [
            'Chemise', 'Costume', 'Pantalon', 'Robe', 'Boubou', 'Culotte',
            'Rideaux', 'Couverture', 'Tapis', 'Chaussures',
        ];

        foreach ($defaults as $i => $name) {
            ArticleType::firstOrCreate(
                ['name' => $name],
                ['code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 6)) ?: 'ART' . ($i + 1), 'sort_order' => $i + 1, 'is_active' => true]
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
