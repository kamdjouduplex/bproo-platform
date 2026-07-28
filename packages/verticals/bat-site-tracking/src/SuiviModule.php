<?php

namespace InovCom\Suivi;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class SuiviModule implements ModuleLifecycle
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
