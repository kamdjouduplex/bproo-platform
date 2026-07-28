<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

class ModuleManager
{
    public function __construct(
        private ModuleRegistry $registry
    ) {}

    public function navLinksForTenant(Tenant $tenant): array
    {
        $links = [];

        // Use cached enabled modules for better performance
        $enabledModuleKeys = $this->registry->getEnabledModules($tenant);

        // Get module details from database, ordered by menu_order (client-centric: Clients first)
        // Virtual nav entries: visible when parent module is enabled (no separate tenant toggle).
        $virtualParents = [
            'prestations' => 'projets',
        ];

        $moduleKeysToShow = collect($enabledModuleKeys);
        foreach ($virtualParents as $child => $parent) {
            if ($moduleKeysToShow->contains($parent)) {
                $moduleKeysToShow->push($child);
            }
        }

        $modules = Module::whereIn('key', $moduleKeysToShow->unique()->all())
            ->orderBy('menu_order')
            ->get(['key', 'label', 'route_name']);

        foreach ($modules as $module) {
            $routeName = $module->route_name;
            if (!$routeName || !Route::has($routeName)) {
                continue;
            }

            $config = config("modules.{$module->key}", []);
            $links[] = [
                'label'      => $module->label,
                'route'      => $routeName,
                'icon'       => $config['icon'] ?? 'folder',
                'permission' => $config['permission'] ?? ($module->key . '.view'),
                'group'      => $config['group'] ?? 'ungrouped',
                'subgroup'   => $config['subgroup'] ?? null,
                'menu_order' => $config['menu_order'] ?? ($module->menu_order ?? 100),
                'route_pattern' => $config['route_pattern'] ?? null,
            ];
        }

        return $links;
    }
}
