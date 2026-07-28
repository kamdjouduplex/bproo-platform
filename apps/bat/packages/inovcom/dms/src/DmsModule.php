<?php

namespace InovCom\Dms;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class DmsModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Migrations run via core_migration_tags at tenant provision
    }

    public function uninstall(object $tenant): void
    {
        // Files on disk are not removed automatically — admin must clean storage
    }
}
