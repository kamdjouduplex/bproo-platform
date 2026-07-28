<?php

namespace InovCom\Projets;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class ProjetsModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Migrations run via core_migration_tags at tenant provision
    }

    public function uninstall(object $tenant): void
    {
        // Optional: drop projects table or leave data
    }
}
