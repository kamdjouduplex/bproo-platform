<?php

namespace InovCom\Facturation;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class FacturationModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Migrations run via core_migration_tags
    }

    public function uninstall(object $tenant): void
    {
        //
    }
}
