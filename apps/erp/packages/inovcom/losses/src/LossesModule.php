<?php

namespace InovCom\Losses;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Losses\Models\LossReason;
use InovCom\Users\Models\Permission;

class LossesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'losses.view', 'name' => 'Voir les pertes', 'description' => 'Accès liste et détail des pertes'],
            ['key' => 'losses.create', 'name' => 'Enregistrer des pertes', 'description' => 'Créer de nouveaux enregistrements de pertes'],
            ['key' => 'losses.update', 'name' => 'Modifier les pertes', 'description' => 'Modifier les pertes existantes'],
            ['key' => 'losses.delete', 'name' => 'Supprimer des pertes', 'description' => 'Supprimer des pertes'],
            ['key' => 'losses.confirm', 'name' => 'Confirmer les pertes', 'description' => 'Confirmer et appliquer les pertes au stock'],
            ['key' => 'reasons.manage', 'name' => 'Gérer les raisons', 'description' => 'Créer, modifier, supprimer les raisons de perte'],
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

        $defaultReasons = [
            ['code' => 'damaged', 'name' => 'Produit endommagé'],
            ['code' => 'expired', 'name' => 'Produit expiré'],
            ['code' => 'theft', 'name' => 'Vol'],
            ['code' => 'breakage', 'name' => 'Casse'],
            ['code' => 'spoilage', 'name' => 'Détérioration'],
            ['code' => 'other', 'name' => 'Autre'],
        ];

        foreach ($defaultReasons as $reason) {
            LossReason::firstOrCreate(
                ['code' => $reason['code']],
                ['name' => $reason['name'], 'is_active' => true]
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup
    }
}
