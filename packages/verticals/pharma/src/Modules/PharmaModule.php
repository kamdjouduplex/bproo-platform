<?php

namespace Pharma\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

/**
 * Vertical entry for Bproo Pharma — seeds pharmacy roles & permissions.
 * Domain features (DCI, mutuelles, fidélité) land here in later phases.
 */
class PharmaModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pharma.view', 'name' => 'Pharmacie — hub', 'description' => 'Accéder au hub Pharmacie'],
            ['key' => 'pharma.manage', 'name' => 'Pharmacie — administrer', 'description' => 'Configurer les options pharmacie'],
        ];
    }

    /**
     * Default role templates for pharmacy tenants.
     *
     * @return list<array{name:string,description:string,permissions:list<string>}>
     */
    public static function defaultRoles(): array
    {
        return [
            [
                'name' => 'pharmacien',
                'description' => 'Pharmacien — vente, stock lots, ordonnances',
                'permissions' => [
                    'pharma.view', 'pharma.manage',
                    'sales.view', 'sales.create', 'sales.update',
                    'stock.view', 'batches.view', 'batches.manage',
                    'prescriptions.view', 'prescriptions.manage',
                    'purchases.view', 'clients.view', 'items.view',
                    'reporting.view', 'reporting.export',
                ],
            ],
            [
                'name' => 'caissier',
                'description' => 'Caissier — POS et caisse',
                'permissions' => [
                    'pharma.view',
                    'sales.view', 'sales.create',
                    'caisse.view', 'caisse.operate',
                    'clients.view', 'items.view',
                ],
            ],
            [
                'name' => 'magasinier',
                'description' => 'Magasinier — stock, lots, achats',
                'permissions' => [
                    'pharma.view',
                    'stock.view', 'stock.update',
                    'batches.view', 'batches.manage',
                    'purchases.view', 'purchases.create',
                    'providers.view', 'items.view',
                    'inventory.view',
                ],
            ],
        ];
    }

    public function install(object $tenant): void
    {
        $ids = [];
        foreach (self::defaultPermissions() as $p) {
            $perm = Permission::on('tenant')->firstOrCreate(
                ['key' => $p['key']],
                ['name' => $p['name'], 'description' => $p['description'] ?? null]
            );
            $perm->fill([
                'name' => $p['name'],
                'description' => $p['description'] ?? null,
            ])->save();
            $ids[] = $perm->id;
        }

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin && $ids !== []) {
            $admin->permissions()->syncWithoutDetaching($ids);
        }

        foreach (self::defaultRoles() as $roleDef) {
            $role = Role::on('tenant')->firstOrCreate(
                ['name' => $roleDef['name']],
                ['description' => $roleDef['description'] ?? null]
            );
            $role->fill(['description' => $roleDef['description'] ?? null])->save();

            $permIds = Permission::on('tenant')
                ->whereIn('key', $roleDef['permissions'])
                ->pluck('id')
                ->all();
            if ($permIds !== []) {
                $role->permissions()->syncWithoutDetaching($permIds);
            }
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep roles/data
    }
}
