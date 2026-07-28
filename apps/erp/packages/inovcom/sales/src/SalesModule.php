<?php

namespace InovCom\Sales;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class SalesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'sales.view', 'name' => 'Voir les ventes', 'description' => 'Accès liste et détail ventes'],
            ['key' => 'sales.create', 'name' => 'Créer des ventes', 'description' => 'Encaisser / Vendre'],
            ['key' => 'sales.update', 'name' => 'Modifier les ventes', 'description' => 'Modifier les ventes existantes'],
            ['key' => 'sales.delete', 'name' => 'Supprimer des ventes', 'description' => 'Supprimer des ventes'],
            ['key' => 'sales.receipt', 'name' => 'Imprimer reçus', 'description' => 'Générer et imprimer des reçus'],
            ['key' => 'sales.modify_price', 'name' => 'Modifier le prix à la vente', 'description' => 'Permet au caissier de modifier le prix lors de l\'encaissement'],
            ['key' => 'sales.return', 'name' => 'Retours produits', 'description' => 'Enregistrer des retours clients (stock et remboursement)'],
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
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup. We keep sales data for now.
    }
}
