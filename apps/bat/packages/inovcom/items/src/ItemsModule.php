<?php

namespace InovCom\Items;

use InovCom\Items\Models\Category;
use InovCom\Items\Models\Unit;
use InovCom\Kernel\Contracts\ModuleLifecycle;

class ItemsModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        Category::firstOrCreate(
            ['name' => 'Divers'],
            ['code' => 'divers', 'is_active' => true]
        );

        Unit::firstOrCreate(
            ['name' => 'Piece'],
            ['abbreviation' => 'pc', 'is_active' => true]
        );
    }

    public function uninstall(object $tenant): void
    {
        // Placeholder for module cleanup logic.
    }
}
