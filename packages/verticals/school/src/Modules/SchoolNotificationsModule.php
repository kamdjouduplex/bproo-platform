<?php

namespace School\Modules;

use InovCom\Kernel\Contracts\ModuleLifecycle;
use School\Support\SchoolNotificationSettings;
use School\Support\SyncsSchoolModulePermissions;

class SchoolNotificationsModule implements ModuleLifecycle
{
    use SyncsSchoolModulePermissions;

    public static function defaultPermissions(): array
    {
        return [
            ['key' => 'school_notifications.view', 'name' => 'Voir les notifications', 'description' => 'Consulter journaux et paramètres'],
            ['key' => 'school_notifications.manage', 'name' => 'Configurer les notifications', 'description' => 'Canaux SMS/Email et modèles'],
        ];
    }

    public function install(object $tenant): void
    {
        self::syncPermissions(self::defaultPermissions(), 'school_notifications');
        SchoolNotificationSettings::seedDefaults();
    }

    public function uninstall(object $tenant): void
    {
        // Keep data
    }
}
