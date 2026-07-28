<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

/**
 * Seeds all standard permissions and the default Admin role into the tenant DB.
 *
 * Run for a specific tenant:
 *   php artisan tenant:seed {tenantCode} --class=TenantPermissionSeeder
 *
 * Safe to re-run — uses firstOrCreate so existing records are preserved.
 */
class TenantPermissionSeeder extends Seeder
{
    /**
     * All permissions grouped by module.
     * Key   → stored in permissions.key (used in tenantAuthorize calls)
     * Name  → human-readable label
     */
    private array $permissions = [
        // ── Clients ───────────────────────────────────────────────────
        ['key' => 'clients.view',   'name' => 'Clients – Voir'],
        ['key' => 'clients.create', 'name' => 'Clients – Créer'],
        ['key' => 'clients.edit',   'name' => 'Clients – Modifier'],
        ['key' => 'clients.delete', 'name' => 'Clients – Supprimer'],

        // ── Offres ────────────────────────────────────────────────────
        ['key' => 'offres.view',    'name' => 'Offres – Voir'],
        ['key' => 'offres.create',  'name' => 'Offres – Créer'],
        ['key' => 'offres.edit',    'name' => 'Offres – Modifier'],
        ['key' => 'offres.delete',  'name' => 'Offres – Supprimer'],
        ['key' => 'offres.close',   'name' => 'Offres – Clôturer (rejeter)'],

        // ── Devis ─────────────────────────────────────────────────────
        ['key' => 'devis.view',     'name' => 'Devis – Voir'],
        ['key' => 'devis.create',   'name' => 'Devis – Créer'],
        ['key' => 'devis.edit',     'name' => 'Devis – Modifier'],
        ['key' => 'devis.delete',   'name' => 'Devis – Supprimer'],
        ['key' => 'devis.send',     'name' => 'Devis – Envoyer'],
        ['key' => 'devis.accept',   'name' => 'Devis – Accepter / Rejeter'],
        ['key' => 'devis.import',   'name' => 'Devis – Importer (Excel/CSV)'],
        ['key' => 'devis.export',   'name' => 'Devis – Exporter (Excel/PDF)'],

        // ── Projets ───────────────────────────────────────────────────
        ['key' => 'projets.view',   'name' => 'Projets – Voir'],
        ['key' => 'projets.create', 'name' => 'Projets – Créer'],
        ['key' => 'projets.edit',   'name' => 'Projets – Modifier'],
        ['key' => 'projets.delete', 'name' => 'Projets – Supprimer'],

        // ── Facturation ───────────────────────────────────────────────
        ['key' => 'facturation.view',   'name' => 'Facturation – Voir'],
        ['key' => 'facturation.create', 'name' => 'Facturation – Créer'],
        ['key' => 'facturation.edit',   'name' => 'Facturation – Modifier'],
        ['key' => 'facturation.delete', 'name' => 'Facturation – Supprimer'],
        ['key' => 'facturation.send',   'name' => 'Facturation – Envoyer'],

        // ── Achats ────────────────────────────────────────────────────
        ['key' => 'achats.view',    'name' => 'Achats – Voir'],
        ['key' => 'achats.create',  'name' => 'Achats – Créer'],
        ['key' => 'achats.edit',    'name' => 'Achats – Modifier'],
        ['key' => 'achats.delete',  'name' => 'Achats – Supprimer'],
        ['key' => 'achats.approve', 'name' => 'Achats – Approuver'],

        // ── Articles (Items) ──────────────────────────────────────────
        ['key' => 'items.view',     'name' => 'Articles – Voir'],
        ['key' => 'items.create',   'name' => 'Articles – Créer'],
        ['key' => 'items.edit',     'name' => 'Articles – Modifier'],
        ['key' => 'items.delete',   'name' => 'Articles – Supprimer'],

        // ── Utilisateurs ──────────────────────────────────────────────
        ['key' => 'users.view',     'name' => 'Utilisateurs – Voir'],
        ['key' => 'users.create',   'name' => 'Utilisateurs – Créer'],
        ['key' => 'users.edit',     'name' => 'Utilisateurs – Modifier'],
        ['key' => 'users.delete',   'name' => 'Utilisateurs – Supprimer'],

        // ── Configuration ─────────────────────────────────────────────
        ['key' => 'configuration.view', 'name' => 'Configuration – Voir'],
        ['key' => 'configuration.edit', 'name' => 'Configuration – Modifier'],

        // ── DMS ───────────────────────────────────────────────────────
        ['key' => 'dms.view',   'name' => 'Documents – Voir'],
        ['key' => 'dms.create', 'name' => 'Documents – Téléverser'],
        ['key' => 'dms.delete', 'name' => 'Documents – Supprimer'],

        // ── Maintenance ───────────────────────────────────────────────
        ['key' => 'maintenance.view',     'name' => 'Maintenance – Voir'],
        ['key' => 'maintenance.create',   'name' => 'Maintenance – Créer'],
        ['key' => 'maintenance.edit',     'name' => 'Maintenance – Modifier'],
        ['key' => 'maintenance.delete',   'name' => 'Maintenance – Supprimer'],
        ['key' => 'maintenance.dispatch', 'name' => 'Maintenance – Affecter / Dispatcher'],
        ['key' => 'maintenance.close',    'name' => 'Maintenance – Clôturer'],

        // ── Rapports ──────────────────────────────────────────────────
        ['key' => 'reports.view', 'name' => 'Rapports – Voir les rapports'],

        // ── Audit ─────────────────────────────────────────────────────
        ['key' => 'audit.view', 'name' => 'Audit – Voir le journal'],

        // ── Suivi terrain ─────────────────────────────────────────────
        ['key' => 'suivi.view',     'name' => 'Suivi terrain – Voir'],
        ['key' => 'suivi.create',   'name' => 'Suivi terrain – Créer'],
        ['key' => 'suivi.edit',     'name' => 'Suivi terrain – Modifier'],
        ['key' => 'suivi.delete',   'name' => 'Suivi terrain – Supprimer'],
        ['key' => 'suivi.validate', 'name' => 'Suivi terrain – Valider'],

        // ── Planning ──────────────────────────────────────────────────
        ['key' => 'planning.view',   'name' => 'Planning – Voir'],
        ['key' => 'planning.create', 'name' => 'Planning – Créer'],
        ['key' => 'planning.edit',   'name' => 'Planning – Modifier'],
        ['key' => 'planning.delete', 'name' => 'Planning – Supprimer'],

        // ── Stock ─────────────────────────────────────────────────────
        ['key' => 'stock.view',   'name' => 'Stock – Voir le tableau de bord et les niveaux'],
        ['key' => 'stock.create', 'name' => 'Stock – Enregistrer des mouvements / créer des produits'],
        ['key' => 'stock.edit',   'name' => 'Stock – Modifier les produits et entrepôts'],
        ['key' => 'stock.delete', 'name' => 'Stock – Supprimer des produits et entrepôts'],

        // ── Logistique ────────────────────────────────────────────────
        ['key' => 'logistique.view',     'name' => 'Logistique – Voir les livraisons'],
        ['key' => 'logistique.create',   'name' => 'Logistique – Créer une livraison'],
        ['key' => 'logistique.edit',     'name' => 'Logistique – Modifier / démarrer une livraison'],
        ['key' => 'logistique.delete',   'name' => 'Logistique – Supprimer véhicules et chauffeurs'],
        ['key' => 'logistique.complete', 'name' => 'Logistique – Compléter une livraison (déduit le stock)'],

        // ── RH & Paie ─────────────────────────────────────────────────
        ['key' => 'rh.view',          'name' => 'RH – Voir les employés'],
        ['key' => 'rh.create',        'name' => 'RH – Créer un employé'],
        ['key' => 'rh.edit',          'name' => 'RH – Modifier un employé'],
        ['key' => 'rh.delete',        'name' => 'RH – Supprimer un employé'],
        ['key' => 'rh.payroll',       'name' => 'RH – Gérer les fiches de paie'],
        ['key' => 'rh.leave_approve', 'name' => 'RH – Approuver / refuser les congés'],
    ];

    public function run(): void
    {
        // ── 1. Seed permissions ───────────────────────────────────────
        $permissionIds = [];
        foreach ($this->permissions as $p) {
            $perm = Permission::firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name']]
            );
            $permissionIds[] = $perm->id;
        }

        // ── 2. Ensure the Admin role exists ───────────────────────────
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Accès complet à tous les modules.']
        );

        // ── 3. Grant ALL permissions to Admin ─────────────────────────
        $adminRole->permissions()->syncWithoutDetaching($permissionIds);

        // ── 4. Ensure a standard User role exists (no permissions by default) ──
        Role::firstOrCreate(
            ['name' => 'Utilisateur'],
            ['description' => 'Accès de base — configurez les permissions dans la matrice.']
        );

        // ── 5. Default permissions per built-in role ──────────────────
        // Uses syncWithoutDetaching so it never revokes manually added permissions.
        $allPerms = Permission::pluck('id', 'key');

        $roleDefaults = [
            // Role name => permission keys
            'Subdirector' => [
                'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                'offres.view', 'offres.create', 'offres.edit', 'offres.delete', 'offres.close',
                'devis.view', 'devis.create', 'devis.edit', 'devis.delete', 'devis.send', 'devis.accept', 'devis.import', 'devis.export',
                'projets.view', 'projets.create', 'projets.edit', 'projets.delete',
                'facturation.view', 'facturation.create', 'facturation.edit', 'facturation.delete', 'facturation.send',
                'achats.view', 'achats.create', 'achats.edit', 'achats.delete', 'achats.approve',
                'items.view', 'items.create', 'items.edit', 'items.delete',
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'dms.view', 'dms.create', 'dms.delete',
                'maintenance.view', 'maintenance.create', 'maintenance.edit', 'maintenance.delete', 'maintenance.dispatch', 'maintenance.close',
                'planning.view', 'planning.create', 'planning.edit', 'planning.delete',
                'suivi.view', 'suivi.create', 'suivi.edit', 'suivi.delete', 'suivi.validate',
                'reports.view', 'audit.view', 'configuration.view', 'configuration.edit',
                'stock.view', 'stock.create', 'stock.edit', 'stock.delete',
                'logistique.view', 'logistique.create', 'logistique.edit', 'logistique.delete', 'logistique.complete',
                'rh.view', 'rh.create', 'rh.edit', 'rh.delete', 'rh.payroll', 'rh.leave_approve',
            ],
            'director' => [
                'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                'offres.view', 'offres.create', 'offres.edit', 'offres.delete', 'offres.close',
                'devis.view', 'devis.create', 'devis.edit', 'devis.delete', 'devis.send', 'devis.accept', 'devis.import', 'devis.export',
                'projets.view', 'projets.create', 'projets.edit', 'projets.delete',
                'facturation.view', 'facturation.create', 'facturation.edit', 'facturation.delete', 'facturation.send',
                'achats.view', 'achats.create', 'achats.edit', 'achats.delete', 'achats.approve',
                'items.view', 'items.create', 'items.edit',
                'dms.view', 'dms.create', 'dms.delete',
                'maintenance.view', 'maintenance.create', 'maintenance.edit', 'maintenance.delete', 'maintenance.dispatch', 'maintenance.close',
                'planning.view', 'planning.create', 'planning.edit', 'planning.delete',
                'suivi.view', 'suivi.create', 'suivi.edit', 'suivi.delete', 'suivi.validate',
                'stock.view', 'stock.create', 'stock.edit', 'stock.delete',
                'logistique.view', 'logistique.create', 'logistique.edit', 'logistique.delete', 'logistique.complete',
                'reports.view', 'audit.view',
                'rh.view', 'rh.create', 'rh.edit', 'rh.delete', 'rh.payroll', 'rh.leave_approve',
            ],
            'project_manager' => [
                'clients.view',
                'offres.view',
                'devis.view',
                'projets.view', 'projets.create', 'projets.edit',
                'achats.view', 'achats.create', 'achats.edit',
                'items.view',
                'dms.view', 'dms.create',
                'maintenance.view', 'maintenance.create', 'maintenance.edit', 'maintenance.dispatch',
                'planning.view', 'planning.create', 'planning.edit', 'planning.delete',
                'suivi.view', 'suivi.create', 'suivi.edit', 'suivi.delete', 'suivi.validate',
                'stock.view', 'stock.create',
                'logistique.view', 'logistique.create', 'logistique.edit', 'logistique.complete',
                'reports.view',
                'rh.view', 'rh.leave_approve',
            ],
            'technician' => [
                'clients.view',
                'projets.view',
                'items.view',
                'dms.view', 'dms.create',
                'maintenance.view', 'maintenance.edit',
                'planning.view',
                'suivi.view', 'suivi.create', 'suivi.edit',
                'stock.view',
                'logistique.view',
                'rh.view',
            ],
            'sales' => [
                'clients.view', 'clients.create', 'clients.edit',
                'offres.view', 'offres.create', 'offres.edit',
                'devis.view', 'devis.create', 'devis.edit', 'devis.send', 'devis.import', 'devis.export',
                'items.view',
                'projets.view',
                'suivi.view',
                'reports.view',
            ],
            'accountant' => [
                'clients.view',
                'devis.view',
                'projets.view',
                'facturation.view', 'facturation.create', 'facturation.edit', 'facturation.send',
                'achats.view', 'achats.approve',
                'suivi.view',
                'reports.view',
                'rh.view', 'rh.payroll',
            ],
        ];

        foreach ($roleDefaults as $roleName => $keys) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }
            $ids = collect($keys)
                ->map(fn($k) => $allPerms->get($k))
                ->filter()
                ->values()
                ->toArray();
            $role->permissions()->syncWithoutDetaching($ids);
        }

        $this->command?->info('Permissions seeded: ' . count($this->permissions));
        $this->command?->info('Admin role granted all permissions.');
        $this->command?->info('Default role permissions applied.');
    }
}
