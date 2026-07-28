<?php

namespace InovCom\Devis;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class DevisModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Migrations run via core_migration_tags at tenant provision
    }

    public function uninstall(object $tenant): void
    {
        // Optional: drop quotes / quote_lines tables or leave data
    }
}
