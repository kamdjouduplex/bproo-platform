<?php

namespace InovCom\Reporting;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class ReportingModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'reporting.view', 'name' => 'Voir les rapports', 'description' => 'Accès aux rapports, filtres et exports'],
            ['key' => 'reporting.export', 'name' => 'Exporter les rapports', 'description' => 'Exporter les données en PDF/Excel'],
        ];
    }

    public function install(object $tenant): void
    {
        $existingKeys = Permission::on('tenant')->pluck('id', 'key')->keys()->flip();
        foreach (self::defaultPermissions() as $p) {
            if (!$existingKeys->has($p['key'])) {
                Permission::on('tenant')->create([
                    'key' => $p['key'],
                    'name' => $p['name'],
                    'description' => $p['description'] ?? null,
                ]);
                $existingKeys->put($p['key'], true);
            }
        }
    }

    public function uninstall(object $tenant): void
    {
        //
    }
}
