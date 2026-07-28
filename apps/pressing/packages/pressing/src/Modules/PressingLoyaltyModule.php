<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;
use Pressing\Support\PressingSettings;

class PressingLoyaltyModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pressing_loyalty.view', 'name' => 'Voir la fidélité', 'description' => 'Consulter points et récompenses clients'],
            ['key' => 'pressing_loyalty.redeem', 'name' => 'Utiliser une récompense', 'description' => 'Appliquer une récompense à une commande'],
            ['key' => 'pressing_loyalty.adjust', 'name' => 'Ajuster les points', 'description' => 'Ajout / retrait manuel de points'],
            ['key' => 'pressing_loyalty.manage', 'name' => 'Configurer la fidélité', 'description' => 'Règles de points et récompenses'],
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
                ->where('key', 'like', 'pressing_loyalty.%')
                ->pluck('id');
            $admin->permissions()->syncWithoutDetaching($ids);
        }

        // Seed sane defaults if the loyalty rules were never configured.
        if ($tenant->getSetting(PressingSettings::KEY_LOYALTY_POINTS_PER_ORDER) === null) {
            $tenant->setSetting(PressingSettings::KEY_LOYALTY_ACTIVE, false);
            $tenant->setSetting(PressingSettings::KEY_LOYALTY_POINTS_PER_ORDER, 1);
            $tenant->setSetting(PressingSettings::KEY_LOYALTY_AMOUNT_PER_POINT, 0);
            $tenant->setSetting(PressingSettings::KEY_LOYALTY_THRESHOLD, 10);
            $tenant->setSetting(PressingSettings::KEY_LOYALTY_REWARD_TYPE, 'value');
            $tenant->setSetting(PressingSettings::KEY_LOYALTY_REWARD_VALUE, 0);
            $tenant->setSetting(PressingSettings::KEY_LOYALTY_REWARD_MAX, '');
            $tenant->setSetting(PressingSettings::KEY_LOYALTY_EXPIRY_DAYS, 0);
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep points, ledger and rewards history.
    }
}
