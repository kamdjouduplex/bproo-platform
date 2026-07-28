<?php

namespace InovCom\Maintenance;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class MaintenanceModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Migrations run via core_migration_tags at tenant provision
    }

    public function uninstall(object $tenant): void
    {
        // Optional: leave data on uninstall
    }
}
