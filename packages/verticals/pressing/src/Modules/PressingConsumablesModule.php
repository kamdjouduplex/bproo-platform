<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;
use Pressing\Services\PressingConsumablesService;

class PressingConsumablesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pressing_consumables.view', 'name' => 'Voir les bons de sortie', 'description' => 'Consulter les sorties atelier'],
            ['key' => 'pressing_consumables.consume', 'name' => 'Créer un bon de sortie', 'description' => 'Enregistrer une sortie atelier'],
            ['key' => 'pressing_consumables.restock', 'name' => 'Entrée de consommables', 'description' => 'Via module Stock'],
            ['key' => 'pressing_consumables.manage', 'name' => 'Gérer les consommables', 'description' => 'Catalogue / seuils via Stock'],
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
            $ids = Permission::on('tenant')
                ->where(function ($q) {
                    $q->where('key', 'like', 'pressing_consumables.%')
                        ->orWhere('key', 'like', 'stock.%')
                        ->orWhere('key', 'like', 'items.%');
                })
                ->pluck('id');
            $admin->permissions()->syncWithoutDetaching($ids);
        }

        // Only seed if items/stock tables exist (deps installed)
        if (class_exists(PressingConsumablesService::class)
            && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('items')
            && \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('stock_levels')) {
            app(PressingConsumablesService::class)->seedCatalog();
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep catalog & stock history
    }
}
