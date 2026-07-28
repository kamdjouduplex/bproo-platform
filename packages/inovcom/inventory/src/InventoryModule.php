<?php

namespace InovCom\Inventory;

use InovCom\Inventory\Models\Reason;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class InventoryModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'inventory.view', 'name' => 'Voir les inventaires', 'description' => 'Accès liste et détail des inventaires'],
            ['key' => 'inventory.create', 'name' => 'Créer des inventaires', 'description' => 'Créer de nouveaux inventaires'],
            ['key' => 'inventory.update', 'name' => 'Modifier les inventaires', 'description' => 'Modifier les inventaires en cours'],
            ['key' => 'inventory.complete', 'name' => 'Finaliser les inventaires', 'description' => 'Finaliser et appliquer les ajustements de stock'],
            ['key' => 'inventory.delete', 'name' => 'Supprimer des inventaires', 'description' => 'Supprimer des inventaires'],
            ['key' => 'adjustments.manage', 'name' => 'Gérer les ajustements', 'description' => 'Créer et gérer les ajustements de stock'],
            ['key' => 'reasons.manage', 'name' => 'Gérer les raisons', 'description' => 'Créer, modifier, supprimer les raisons d\'ajustement'],
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

        // Create default reasons
        $defaultReasons = [
            ['code' => 'damaged', 'name' => 'Produit endommagé', 'type' => 'loss'],
            ['code' => 'expired', 'name' => 'Produit expiré', 'type' => 'loss'],
            ['code' => 'theft', 'name' => 'Vol', 'type' => 'loss'],
            ['code' => 'found', 'name' => 'Produit trouvé', 'type' => 'gain'],
            ['code' => 'error', 'name' => 'Erreur de saisie', 'type' => 'adjustment'],
            ['code' => 'other', 'name' => 'Autre', 'type' => 'adjustment'],
        ];

        foreach ($defaultReasons as $reason) {
            Reason::firstOrCreate(
                ['code' => $reason['code']],
                [
                    'name' => $reason['name'],
                    'type' => $reason['type'],
                    'is_active' => true,
                ]
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup. We keep inventory data for now.
    }
}
