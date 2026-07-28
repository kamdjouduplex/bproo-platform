<?php

namespace InovCom\Items;

use InovCom\Items\Models\Category;
use InovCom\Items\Models\Unit;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class ItemsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'items.view', 'name' => 'Voir les articles', 'description' => 'Consulter le catalogue'],
            ['key' => 'items.create', 'name' => 'Créer des articles', 'description' => 'Ajouter des articles'],
            ['key' => 'items.update', 'name' => 'Modifier les articles', 'description' => 'Modifier des articles'],
            ['key' => 'items.delete', 'name' => 'Supprimer des articles', 'description' => 'Supprimer des articles'],
            ['key' => 'items.configure_list', 'name' => 'Configurer la liste articles', 'description' => 'Colonnes visibles et ordre sur la liste des articles'],
            ['key' => 'items.view_cost', 'name' => 'Voir le coût d\'achat', 'description' => 'Afficher le prix d\'achat / coût sur la liste et la fiche article'],
        ];
    }

    public function install(object $tenant): void
    {
        Category::firstOrCreate(
            ['name' => 'Divers'],
            ['code' => 'divers', 'is_active' => true]
        );

        Unit::firstOrCreate(
            ['name' => 'Piece'],
            ['abbreviation' => 'pc', 'is_active' => true]
        );

        foreach (self::defaultPermissions() as $p) {
            Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }
    }

    public function uninstall(object $tenant): void
    {
        // Placeholder for module cleanup logic.
    }
}
