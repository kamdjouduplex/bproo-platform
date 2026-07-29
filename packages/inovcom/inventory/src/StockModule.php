<?php

namespace InovCom\Stock;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class StockModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'stock.view', 'name' => 'Voir le stock', 'description' => 'Accès liste et détail stock'],
            ['key' => 'stock.adjust', 'name' => 'Ajuster le stock', 'description' => 'Ajuster les niveaux de stock'],
            ['key' => 'stock.movements', 'name' => 'Voir mouvements', 'description' => 'Consulter l\'historique des mouvements'],
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
        // Optional: soft cleanup. We keep stock data for now.
    }
}
