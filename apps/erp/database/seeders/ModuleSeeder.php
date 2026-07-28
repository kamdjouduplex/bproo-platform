<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (Module::count() > 0) {
            return;
        }

        $modules = collect(config('modules', []))
            ->except('core_migration_tags')
            ->filter(fn ($m) => is_array($m) && isset($m['label']));

        foreach ($modules as $key => $module) {
            Module::create([
                'key' => $key,
                'label' => $module['label'],
                'description' => $module['description'] ?? null,
                'route_name' => $module['route_name'] ?? null,
                'lifecycle_handler' => $module['lifecycle_handler'] ?? null,
                'enabled_by_default' => $module['enabled_by_default'] ?? false,
            ]);
        }
    }
}
