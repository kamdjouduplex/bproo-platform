<?php

namespace InovCom\Purchases;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class PurchasesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'purchases.manage', 'name' => 'Gérer les achats (complet)', 'description' => 'Toutes les actions : créer, confirmer, réceptionner, annuler'],
            ['key' => 'purchases.view', 'name' => 'Voir les achats', 'description' => 'Accès liste et détail commandes'],
            ['key' => 'purchases.create', 'name' => 'Créer des commandes', 'description' => 'Créer de nouvelles commandes d\'achat'],
            ['key' => 'purchases.update', 'name' => 'Modifier les commandes', 'description' => 'Modifier les commandes existantes'],
            ['key' => 'purchases.receive', 'name' => 'Réceptionner les marchandises', 'description' => 'Créer des bons de réception et mettre à jour le stock'],
            ['key' => 'purchases.confirm', 'name' => 'Confirmer les commandes', 'description' => 'Valider une commande et enregistrer les prix d\'achat'],
            ['key' => 'purchases.cancel', 'name' => 'Annuler les commandes', 'description' => 'Annulation partielle ou totale d\'une commande'],
            ['key' => 'purchases.delete', 'name' => 'Supprimer des commandes', 'description' => 'Supprimer des commandes'],
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

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin) {
            $ids = Permission::on('tenant')->where('key', 'like', 'purchases.%')->pluck('id');
            $admin->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup. We keep purchases data for now.
    }
}
