<?php

namespace InovCom\Users;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class UsersModule implements ModuleLifecycle
{
    /**
     * Full ERP permission set.
     * Key format: <module>.<action>
     */
    public static function defaultPermissions(): array
    {
        return [
            // ── Clients ───────────────────────────────────────────────
            ['key' => 'clients.view',   'name' => 'Voir les clients',      'description' => 'Accès liste et fiche client'],
            ['key' => 'clients.create', 'name' => 'Créer des clients',     'description' => 'Créer de nouveaux clients'],
            ['key' => 'clients.edit',   'name' => 'Modifier les clients',  'description' => 'Modifier les fiches client'],
            ['key' => 'clients.delete', 'name' => 'Supprimer des clients', 'description' => 'Supprimer des clients'],
            ['key' => 'clients.export', 'name' => 'Exporter les clients',  'description' => 'Exporter la liste clients'],

            // ── Offres ────────────────────────────────────────────────
            ['key' => 'offres.view',   'name' => 'Voir les offres',      'description' => 'Accès liste et détail offres'],
            ['key' => 'offres.create', 'name' => 'Créer des offres',     'description' => 'Saisir de nouvelles offres'],
            ['key' => 'offres.edit',   'name' => 'Modifier les offres',  'description' => 'Modifier les offres'],
            ['key' => 'offres.delete', 'name' => 'Supprimer des offres', 'description' => 'Supprimer des offres'],

            // ── Devis ─────────────────────────────────────────────────
            ['key' => 'devis.view',   'name' => 'Voir les devis',       'description' => 'Accès liste et détail devis'],
            ['key' => 'devis.create', 'name' => 'Créer des devis',      'description' => 'Créer de nouveaux devis'],
            ['key' => 'devis.edit',   'name' => 'Modifier les devis',   'description' => 'Modifier les devis'],
            ['key' => 'devis.delete', 'name' => 'Supprimer des devis',  'description' => 'Supprimer des devis'],
            ['key' => 'devis.send',   'name' => 'Envoyer des devis',    'description' => 'Envoyer un devis au client'],
            ['key' => 'devis.accept', 'name' => 'Accepter/Refuser un devis', 'description' => 'Valider ou refuser un devis'],

            // ── Projets ───────────────────────────────────────────────
            ['key' => 'projets.view',   'name' => 'Voir les projets',      'description' => 'Accès liste et détail projets'],
            ['key' => 'projets.create', 'name' => 'Créer des projets',     'description' => 'Créer de nouveaux projets'],
            ['key' => 'projets.edit',   'name' => 'Modifier les projets',  'description' => 'Modifier les projets'],
            ['key' => 'projets.delete', 'name' => 'Supprimer des projets', 'description' => 'Supprimer des projets'],
            ['key' => 'projets.manage', 'name' => 'Gérer les projets',     'description' => 'Phases, tâches, membres d\'équipe'],

            // ── Facturation ───────────────────────────────────────────
            ['key' => 'facturation.view',           'name' => 'Voir les factures',           'description' => 'Accès liste et détail factures'],
            ['key' => 'facturation.create',         'name' => 'Créer des factures',           'description' => 'Créer de nouvelles factures'],
            ['key' => 'facturation.edit',           'name' => 'Modifier les factures',        'description' => 'Modifier les factures'],
            ['key' => 'facturation.delete',         'name' => 'Supprimer des factures',       'description' => 'Supprimer des factures'],
            ['key' => 'facturation.send',           'name' => 'Envoyer des factures',         'description' => 'Envoyer une facture au client'],
            ['key' => 'facturation.record_payment', 'name' => 'Enregistrer un paiement',      'description' => 'Saisir un règlement sur une facture'],
            ['key' => 'facturation.cancel',         'name' => 'Annuler des factures',         'description' => 'Annuler ou émettre un avoir'],

            // ── Achats ────────────────────────────────────────────────
            ['key' => 'achats.view',     'name' => 'Voir les achats',          'description' => 'Accès bons de commande et fournisseurs'],
            ['key' => 'achats.create',   'name' => 'Créer des bons d\'achat',  'description' => 'Créer de nouveaux bons de commande'],
            ['key' => 'achats.edit',     'name' => 'Modifier les achats',       'description' => 'Modifier les bons de commande'],
            ['key' => 'achats.delete',   'name' => 'Supprimer des achats',      'description' => 'Supprimer des bons de commande'],
            ['key' => 'achats.validate', 'name' => 'Valider les achats',        'description' => 'Approuver / valider un bon de commande'],
            ['key' => 'achats.receive',  'name' => 'Réceptionner des achats',   'description' => 'Enregistrer la réception de marchandises'],

            // ── Articles ──────────────────────────────────────────────
            ['key' => 'items.view',   'name' => 'Voir les articles',      'description' => 'Consulter le catalogue'],
            ['key' => 'items.create', 'name' => 'Créer des articles',     'description' => 'Ajouter des articles au catalogue'],
            ['key' => 'items.edit',   'name' => 'Modifier les articles',  'description' => 'Modifier des articles'],
            ['key' => 'items.delete', 'name' => 'Supprimer des articles', 'description' => 'Supprimer des articles'],

            // ── Rapports ──────────────────────────────────────────────
            ['key' => 'reports.view',     'name' => 'Voir les rapports',          'description' => 'Accès aux tableaux de bord et rapports'],
            ['key' => 'reports.export',   'name' => 'Exporter des rapports',      'description' => 'Télécharger PDF / Excel'],
            ['key' => 'reports.schedule', 'name' => 'Planifier des rapports',     'description' => 'Programmer l\'envoi automatique de rapports'],

            // ── Documents ─────────────────────────────────────────────
            ['key' => 'documents.view',   'name' => 'Voir les documents',    'description' => 'Accès à la GED'],
            ['key' => 'documents.upload', 'name' => 'Téléverser des documents', 'description' => 'Ajouter des fichiers'],
            ['key' => 'documents.delete', 'name' => 'Supprimer des documents',  'description' => 'Supprimer des fichiers'],

            // ── Utilisateurs & Configuration ──────────────────────────
            ['key' => 'users.view',         'name' => 'Voir les utilisateurs',    'description' => 'Accès liste utilisateurs'],
            ['key' => 'users.create',        'name' => 'Créer des utilisateurs',   'description' => 'Créer de nouveaux utilisateurs'],
            ['key' => 'users.edit',          'name' => 'Modifier les utilisateurs','description' => 'Modifier les utilisateurs'],
            ['key' => 'users.delete',        'name' => 'Supprimer des utilisateurs','description' => 'Supprimer des utilisateurs'],
            ['key' => 'roles.view',          'name' => 'Voir les rôles',           'description' => 'Accès liste des rôles'],
            ['key' => 'roles.manage',        'name' => 'Gérer les rôles',          'description' => 'Créer, modifier, supprimer rôles et permissions'],
            ['key' => 'configuration.view',  'name' => 'Voir la configuration',    'description' => 'Accès aux paramètres du tenant'],
            ['key' => 'configuration.edit',  'name' => 'Modifier la configuration','description' => 'Modifier les paramètres du tenant'],
        ];
    }

    /**
     * Role definitions: name → [description, permission keys[]]
     * 'admin' gets ALL permissions automatically.
     */
    private static function roleDefinitions(): array
    {
        return [
            'admin' => [
                'description' => 'Administrateur — accès complet',
                'permissions'  => '*', // All permissions
            ],
            'director' => [
                'description' => 'Directeur — accès complet sauf gestion utilisateurs',
                'permissions'  => [
                    'clients.*', 'offres.*', 'devis.*', 'projets.*',
                    'facturation.*', 'achats.*', 'items.*',
                    'reports.*', 'documents.*',
                    'configuration.view',
                ],
            ],
            'project_manager' => [
                'description' => 'Chef de Projet — projets, offres, devis, achats',
                'permissions'  => [
                    'clients.view', 'clients.create', 'clients.edit',
                    'offres.*',
                    'devis.view', 'devis.create', 'devis.edit', 'devis.send', 'devis.accept',
                    'projets.*',
                    'facturation.view',
                    'achats.view', 'achats.create', 'achats.edit',
                    'items.view',
                    'reports.view', 'reports.export',
                    'documents.*',
                ],
            ],
            'sales' => [
                'description' => 'Commercial — clients, offres, devis',
                'permissions'  => [
                    'clients.view', 'clients.create', 'clients.edit',
                    'offres.*',
                    'devis.view', 'devis.create', 'devis.edit', 'devis.send', 'devis.accept',
                    'projets.view',
                    'facturation.view',
                    'items.view',
                    'reports.view',
                    'documents.view', 'documents.upload',
                ],
            ],
            'accountant' => [
                'description' => 'Comptable — facturation, achats, rapports financiers',
                'permissions'  => [
                    'clients.view',
                    'devis.view',
                    'projets.view',
                    'facturation.*',
                    'achats.view', 'achats.validate', 'achats.receive',
                    'reports.view', 'reports.export', 'reports.schedule',
                    'documents.view', 'documents.upload',
                ],
            ],
            'technician' => [
                'description' => 'Technicien — projets et interventions en lecture / exécution',
                'permissions'  => [
                    'projets.view',
                    'items.view',
                    'documents.view', 'documents.upload',
                ],
            ],
        ];
    }

    public function install(object $tenant): void
    {
        // 1. Create all permissions.
        foreach (self::defaultPermissions() as $p) {
            Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
        }

        // 2. Create each role and assign its permissions.
        foreach (self::roleDefinitions() as $roleName => $def) {
            $role = Role::on('tenant')->firstOrCreate(
                ['name' => $roleName],
                ['description' => $def['description']]
            );

            if ($def['permissions'] === '*') {
                $role->permissions()->sync(Permission::on('tenant')->pluck('id'));
            } else {
                $permissionIds = static::resolvePermissionIds($def['permissions']);
                $role->permissions()->sync($permissionIds);
            }
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep roles/permissions; removing them would lock users out.
    }

    /**
     * Resolve an array of permission key patterns (with optional '*' wildcard)
     * to a collection of permission IDs.
     *
     * @param  string[]  $patterns  e.g. ['clients.*', 'devis.view']
     * @return \Illuminate\Support\Collection<int>
     */
    private static function resolvePermissionIds(array $patterns): \Illuminate\Support\Collection
    {
        $allPermissions = Permission::on('tenant')->get(['id', 'key']);
        $ids = collect();

        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = rtrim($pattern, '*');
                $matched = $allPermissions->filter(fn ($p) => str_starts_with($p->key, $prefix));
            } else {
                $matched = $allPermissions->where('key', $pattern);
            }
            $ids = $ids->merge($matched->pluck('id'));
        }

        return $ids->unique();
    }
}
