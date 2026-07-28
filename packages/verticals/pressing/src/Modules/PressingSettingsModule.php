<?php

namespace Pressing\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use InovCom\Users\Models\Permission;
use Pressing\Support\PressingSettings;

class PressingSettingsModule implements ModuleLifecycle
{
    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'pressing_settings.view', 'name' => 'Voir le paramétrage pressing', 'description' => 'Accès au hub de paramétrage'],
            ['key' => 'pressing_settings.manage', 'name' => 'Gérer le paramétrage pressing', 'description' => 'Modifier types, tarifs, délais, taxes, messages, paiements'],
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

        PressingSettings::seedDefaults($tenant);
    }

    public function uninstall(object $tenant): void
    {
        // Keep settings
    }
}
