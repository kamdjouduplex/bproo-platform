<?php

namespace InovCom\Offres;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class OffresModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Migrations run via migration_tag at tenant enable
    }

    public function uninstall(object $tenant): void
    {
        // Optional: drop offers table or leave data
    }
}
