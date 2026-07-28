<?php

namespace InovCom\Configuration;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;

class ConfigurationModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'configuration.view', 'name' => 'Voir la configuration', 'description' => 'Accès aux paramètres'],
            ['key' => 'configuration.edit', 'name' => 'Modifier la configuration', 'description' => 'Modifier les paramètres système'],
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
