<?php

namespace InovCom\Caisse;

use InovCom\Caisse\Services\CaisseService;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class CaisseModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'caisse.view', 'name' => 'Voir la caisse', 'description' => 'Consulter le journal, les sessions et exporter PDF/Excel'],
            ['key' => 'caisse.open', 'name' => 'Ouvrir la caisse', 'description' => 'Ouvrir une session avec le fond de caisse initial'],
            ['key' => 'caisse.cash_in', 'name' => 'Entrée de caisse', 'description' => 'Enregistrer une entrée manuelle pendant une session ouverte'],
            ['key' => 'caisse.cash_out', 'name' => 'Sortie de caisse', 'description' => 'Enregistrer une sortie manuelle pendant une session ouverte'],
            ['key' => 'caisse.close', 'name' => 'Clôturer la caisse', 'description' => 'Clôturer la session en cours avec le montant compté'],
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

        // Ensure an initial ledger exists for new tenants with the module enabled.
        app(CaisseService::class)->ensureLedgerInitialized();
    }

    public function uninstall(object $tenant): void
    {
        // Keep accounting data for audit consistency.
    }
}
