<?php

namespace InovCom\Clients;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class ClientsModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Migrations run via core_migration_tags at tenant provision
    }

    public function uninstall(object $tenant): void
    {
        // Optional: drop clients table or leave data
    }
}
