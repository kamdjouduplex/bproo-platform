<?php

namespace Pharma\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use InovCom\Users\Models\Role;

class PharmaReportingModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'reporting.view', 'name' => 'Voir les rapports', 'description' => 'Accès au rapport pharmacie'],
            ['key' => 'reporting.export', 'name' => 'Exporter les rapports', 'description' => 'Exporter Excel / PDF'],
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
            $ids[] = $perm->id;
        }

        $admin = Role::on('tenant')->where('name', 'admin')->first();
        if ($admin && $ids !== []) {
            $admin->permissions()->syncWithoutDetaching($ids);
        }

        $pharmacien = Role::on('tenant')->where('name', 'pharmacien')->first();
        if ($pharmacien && $ids !== []) {
            $pharmacien->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function uninstall(object $tenant): void
    {
        // Keep data and permissions
    }
}
