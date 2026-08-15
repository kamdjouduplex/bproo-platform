<?php

namespace InovCom\Expenses;

use InovCom\Expenses\Models\ExpenseCategory;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class ExpensesModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'expenses.view', 'name' => 'Voir les dépenses', 'description' => 'Accès liste et détail des dépenses'],
            ['key' => 'expenses.create', 'name' => 'Créer des dépenses', 'description' => 'Créer de nouvelles dépenses'],
            ['key' => 'expenses.update', 'name' => 'Modifier les dépenses', 'description' => 'Modifier les dépenses existantes'],
            ['key' => 'expenses.delete', 'name' => 'Supprimer des dépenses', 'description' => 'Supprimer des dépenses'],
            ['key' => 'expenses.approve', 'name' => 'Approuver des dépenses', 'description' => 'Approuver ou rejeter des dépenses'],
            ['key' => 'categories.manage', 'name' => 'Gérer les catégories', 'description' => 'Créer, modifier, supprimer les catégories de dépenses'],
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

        self::syncDefaultCategories();
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup. We keep expenses data for now.
    }

    /**
     * @return list<array{code: string, name: string, description: string}>
     */
    public static function defaultCategories(): array
    {
        return [
            ['code' => 'rent', 'name' => 'Loyer', 'description' => 'Loyer et charges locatives'],
            ['code' => 'utilities', 'name' => 'Services publics', 'description' => 'Électricité, eau, téléphone, internet'],
            ['code' => 'salaries', 'name' => 'Salaires', 'description' => 'Salaires et rémunérations'],
            ['code' => 'transport', 'name' => 'Transport', 'description' => 'Frais de transport et carburant'],
            ['code' => 'marketing', 'name' => 'Marketing', 'description' => 'Publicité et promotion'],
            ['code' => 'maintenance', 'name' => 'Maintenance', 'description' => 'Réparations et entretien'],
            ['code' => 'supplies', 'name' => 'Fournitures', 'description' => 'Fournitures de bureau et autres'],
            ['code' => 'connexion', 'name' => 'Connexion', 'description' => 'Internet, données mobiles et frais de connexion'],
            ['code' => 'restauration', 'name' => 'Restauration', 'description' => 'Repas et restauration'],
            ['code' => 'other', 'name' => 'Autres', 'description' => 'Autres dépenses'],
        ];
    }

    /**
     * Idempotent — safe for existing tenants (does not remove existing categories).
     */
    public static function syncDefaultCategories(): void
    {
        foreach (self::defaultCategories() as $category) {
            ExpenseCategory::firstOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
