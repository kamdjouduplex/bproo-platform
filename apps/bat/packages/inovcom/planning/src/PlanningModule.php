<?php

namespace InovCom\Planning;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class PlanningModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Migrations run via core_migration_tags at tenant provision
    }

    public function uninstall(object $tenant): void
    {
        // Leave data on uninstall
    }
}
