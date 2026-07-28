<?php

namespace InovCom\Purchases;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class ForeignPurchasesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'foreign_purchases.manage', 'name' => 'Gérer les achats étrangers (complet)', 'description' => 'Toutes les actions sur les achats en devises'],
            ['key' => 'foreign_purchases.view', 'name' => 'Voir les achats étrangers', 'description' => 'Accès liste et détail'],
            ['key' => 'foreign_purchases.create', 'name' => 'Créer des achats étrangers', 'description' => 'Créer des commandes en devises'],
            ['key' => 'foreign_purchases.update', 'name' => 'Modifier les achats étrangers', 'description' => 'Modifier les commandes brouillon'],
            ['key' => 'foreign_purchases.confirm', 'name' => 'Confirmer les achats étrangers', 'description' => 'Valider la commande et enregistrer les coûts'],
            ['key' => 'foreign_purchases.receive', 'name' => 'Réceptionner les achats étrangers', 'description' => 'Réception partielle ou totale et mise à jour du stock'],
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
            $ids = Permission::on('tenant')->where('key', 'like', 'foreign_purchases.%')->pluck('id');
            $admin->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep data on uninstall.
    }
}
