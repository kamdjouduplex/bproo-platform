<?php

namespace InovCom\Users;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class UsersModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'users.view', 'name' => 'Voir les utilisateurs', 'description' => 'Accès liste et détail utilisateurs'],
            ['key' => 'users.create', 'name' => 'Créer des utilisateurs', 'description' => 'Créer de nouveaux utilisateurs'],
            ['key' => 'users.update', 'name' => 'Modifier les utilisateurs', 'description' => 'Modifier les utilisateurs existants'],
            ['key' => 'users.delete', 'name' => 'Supprimer des utilisateurs', 'description' => 'Supprimer des utilisateurs'],
            ['key' => 'roles.view', 'name' => 'Voir les rôles', 'description' => 'Accès liste et détail rôles'],
            ['key' => 'roles.manage', 'name' => 'Gérer les rôles', 'description' => 'Créer, modifier, supprimer rôles et permissions'],
            ['key' => 'configuration.view', 'name' => 'Voir la configuration', 'description' => 'Accès au module configuration (paramètres, personnalisation)'],
            ['key' => 'items.view', 'name' => 'Voir les articles', 'description' => 'Consulter le catalogue'],
            ['key' => 'items.create', 'name' => 'Créer des articles', 'description' => 'Ajouter des articles'],
            ['key' => 'items.update', 'name' => 'Modifier les articles', 'description' => 'Modifier des articles'],
            ['key' => 'items.delete', 'name' => 'Supprimer des articles', 'description' => 'Supprimer des articles'],
            ['key' => 'items.configure_list', 'name' => 'Configurer la liste articles', 'description' => 'Colonnes visibles et ordre sur la liste des articles'],
            ['key' => 'items.view_cost', 'name' => 'Voir le coût d\'achat', 'description' => 'Afficher le prix d\'achat / coût sur la liste et la fiche article'],
            ['key' => 'clients.view', 'name' => 'Voir les clients', 'description' => 'Accès liste et détail clients'],
            ['key' => 'clients.create', 'name' => 'Créer des clients', 'description' => 'Créer des clients'],
            ['key' => 'clients.update', 'name' => 'Modifier les clients', 'description' => 'Modifier des clients'],
            ['key' => 'providers.view', 'name' => 'Voir les fournisseurs', 'description' => 'Accès liste et détail fournisseurs'],
            ['key' => 'providers.create', 'name' => 'Créer des fournisseurs', 'description' => 'Créer des fournisseurs'],
            ['key' => 'providers.update', 'name' => 'Modifier les fournisseurs', 'description' => 'Modifier des fournisseurs'],
            ['key' => 'sales.view', 'name' => 'Voir les ventes', 'description' => 'Accès liste et détail des ventes'],
            ['key' => 'sales.create', 'name' => 'Encaisser / Vendre', 'description' => 'Créer des ventes (ex. caissier)'],
            ['key' => 'sales.modify_price', 'name' => 'Modifier le prix à la vente', 'description' => 'Permet au caissier de modifier le prix lors de l\'encaissement'],
            ['key' => 'stock.view', 'name' => 'Voir le stock', 'description' => 'Consulter les niveaux de stock'],
            ['key' => 'stock.manage', 'name' => 'Gérer le stock', 'description' => 'Ajuster et gérer le stock'],
            ['key' => 'purchases.view', 'name' => 'Voir les achats', 'description' => 'Accès aux commandes d\'achat'],
            ['key' => 'purchases.manage', 'name' => 'Gérer les achats', 'description' => 'Créer et gérer les achats'],
            ['key' => 'inventory.view', 'name' => 'Voir l\'inventaire', 'description' => 'Accès au module inventaire'],
            ['key' => 'inventory.manage', 'name' => 'Gérer l\'inventaire', 'description' => 'Comptages et ajustements'],
            ['key' => 'expenses.view', 'name' => 'Voir les dépenses', 'description' => 'Accès au module dépenses'],
            ['key' => 'expenses.manage', 'name' => 'Gérer les dépenses', 'description' => 'Créer et approuver les dépenses'],
            ['key' => 'losses.view', 'name' => 'Voir les pertes', 'description' => 'Accès au module pertes'],
            ['key' => 'losses.manage', 'name' => 'Gérer les pertes', 'description' => 'Enregistrer les pertes'],
            ['key' => 'debts.view', 'name' => 'Voir les dettes', 'description' => 'Accès au module dettes'],
            ['key' => 'debts.manage', 'name' => 'Gérer les dettes', 'description' => 'Gérer dettes et paiements'],
            ['key' => 'reporting.view', 'name' => 'Voir les rapports', 'description' => 'Accès aux rapports et analyses'],
            ['key' => 'stores.view_all', 'name' => 'Voir toutes les boutiques', 'description' => 'Peut consulter toutes les boutiques et changer de contexte magasin'],
            ['key' => 'payroll.view', 'name' => 'Voir la paie', 'description' => 'Accès au module paie'],
            ['key' => 'payroll.manage', 'name' => 'Gérer la paie', 'description' => 'Gérer fiches de paie'],
            ['key' => 'configuration.manage', 'name' => 'Gérer la configuration', 'description' => 'Modifier la configuration'],
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

        $admin = Role::on('tenant')->firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrateur tenant – accès complet']
        );
        $admin->permissions()->sync(
            Permission::on('tenant')->pluck('id')
        );

        $cashier = Role::on('tenant')->firstOrCreate(
            ['name' => 'cashier'],
            ['description' => 'Caissier – ventes et articles en lecture']
        );
        $cashier->permissions()->sync(
            Permission::on('tenant')->whereIn('key', ['sales.view', 'sales.create', 'items.view'])->pluck('id')
        );
    }

    public function uninstall(object $tenant): void
    {
        // Optional: soft cleanup. We keep roles/permissions for now.
    }
}
