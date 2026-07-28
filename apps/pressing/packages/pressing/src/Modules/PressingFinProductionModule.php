<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class PressingFinProductionModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pressing_fin_production.view', 'name' => 'Voir fin de production', 'description' => 'Contrôle qualité et emballage'],
            ['key' => 'pressing_fin_production.process', 'name' => 'Traiter fin de production', 'description' => 'Valider CQ, emballer, envoyer en livraison'],
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
    }

    public function uninstall(object $tenant): void
    {
        //
    }
}
