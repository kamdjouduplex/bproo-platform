<?php

namespace Pressing\Support;

use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

/**
 * Canonical pressing role → permission matrix.
 * Syncs both English seeded roles and French UI-created role names.
 */
final class PressingRolePermissions
{
    /**
     * Exact permission keys per logical profile.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return [
            'reception' => [
                'attendance.punch',
                'attendance.view',
                // Clients
                'pressing_clients.view',
                'pressing_clients.create',
                'pressing_clients.update',
                // Reception / caisse
                'pressing_orders.view',
                'pressing_orders.create',
                'pressing_orders.update',
                'pressing_orders.sort',
                'pressing_orders.pay',
                'pressing_orders.request_credit',
                // Deliveries (plan + hand over at counter)
                'pressing_deliveries.view',
                'pressing_deliveries.create',
                'pressing_deliveries.update',
                // Loyalty at reception
                'pressing_loyalty.view',
                'pressing_loyalty.redeem',
                // Read-only pipeline
                'pressing_workflow.view',
            ],
            'production' => [
                'attendance.punch',
                'attendance.view',
                'pressing_orders.view',
                'pressing_workflow.view',
                'pressing_workflow.move',
                'pressing_fin_production.view',
                'pressing_fin_production.process',
                'pressing_consumables.view',
                'pressing_consumables.consume',
            ],
            'repassage' => [
                'attendance.punch',
                'attendance.view',
                'pressing_orders.view',
                'pressing_workflow.view',
                'pressing_workflow.move',
                'pressing_fin_production.view',
                'pressing_fin_production.process',
                'pressing_consumables.view',
                'pressing_consumables.consume',
            ],
            'driver' => [
                'attendance.punch',
                'attendance.view',
                'pressing_deliveries.view',
                'pressing_deliveries.update',
            ],
        ];
    }

    /**
     * Role names (DB) that map to each profile.
     *
     * @return array<string, list<string>>
     */
    public static function roleNamesByProfile(): array
    {
        return [
            'reception' => ['pressing_reception', 'Réception', 'cashier', 'reception', 'receptionist'],
            'production' => ['pressing_production', 'Production', 'production', 'presser', 'atelier'],
            'repassage' => ['Repassage', 'repassage'],
            'driver' => ['pressing_driver', 'Livreur', 'livreur', 'driver'],
        ];
    }

    /**
     * Sync all known pressing operational roles. Returns counts per role name.
     *
     * @return array<string, int>
     */
    public static function syncAll(): array
    {
        $matrix = self::matrix();
        $map = self::roleNamesByProfile();
        $synced = [];

        foreach ($map as $profile => $names) {
            $keys = $matrix[$profile] ?? [];
            $ids = Permission::on('tenant')->whereIn('key', $keys)->pluck('id');

            foreach ($names as $name) {
                $role = Role::on('tenant')->where('name', $name)->first();
                if (! $role) {
                    continue;
                }
                $role->permissions()->sync($ids);
                $synced[$name] = $ids->count();
            }
        }

        return $synced;
    }
}
