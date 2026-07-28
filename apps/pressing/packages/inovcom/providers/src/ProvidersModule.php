<?php

namespace InovCom\Providers;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Providers\Models\PaymentTerm;
use InovCom\Users\Models\Permission;

class ProvidersModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'providers.view', 'name' => 'Voir les fournisseurs', 'description' => 'Accès liste et détail fournisseurs'],
            ['key' => 'providers.create', 'name' => 'Créer des fournisseurs', 'description' => 'Créer de nouveaux fournisseurs'],
            ['key' => 'providers.update', 'name' => 'Modifier les fournisseurs', 'description' => 'Modifier les fournisseurs existants'],
            ['key' => 'providers.delete', 'name' => 'Supprimer des fournisseurs', 'description' => 'Supprimer des fournisseurs'],
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

        // Create default payment terms common in African market
        PaymentTerm::firstOrCreate(
            ['name' => 'Comptant'],
            ['days' => 0, 'description' => 'Paiement immédiat', 'is_active' => true]
        );

        PaymentTerm::firstOrCreate(
            ['name' => 'Net 30'],
            ['days' => 30, 'description' => 'Paiement dans 30 jours', 'is_active' => true]
        );

        PaymentTerm::firstOrCreate(
            ['name' => 'Net 60'],
            ['days' => 60, 'description' => 'Paiement dans 60 jours', 'is_active' => true]
        );
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup. We keep providers data for now.
    }
}
