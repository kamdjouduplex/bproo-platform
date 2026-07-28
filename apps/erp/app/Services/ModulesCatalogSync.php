<?php

namespace App\Services;

use App\Models\Module;

class ModulesCatalogSync
{
    /**
     * Sync module metadata from config/modules.php.
     * When $preserveDefaults is true, existing enabled_by_default values are kept.
     */
    public static function syncFromConfig(bool $preserveDefaults = true): void
    {
        $modules = collect(config('modules', []))
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && isset($m['label']));

        foreach ($modules as $key => $config) {
            $attributes = [
                'label' => $config['label'],
                'description' => $config['description'] ?? null,
                'route_name' => $config['route_name'] ?? null,
                'lifecycle_handler' => $config['lifecycle_handler'] ?? null,
            ];

            if (!$preserveDefaults) {
                $attributes['enabled_by_default'] = (bool) ($config['enabled_by_default'] ?? false);
            }

            $module = Module::firstOrNew(['key' => $key]);
            if (!$module->exists) {
                $module->fill(array_merge($attributes, [
                    'enabled_by_default' => (bool) ($config['enabled_by_default'] ?? false),
                ]));
                $module->save();
                continue;
            }

            $module->fill($attributes);
            $module->save();
        }
    }
}
