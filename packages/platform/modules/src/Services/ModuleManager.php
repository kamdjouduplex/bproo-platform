<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

class ModuleManager
{
    public function __construct(
        private ModuleRegistry $registry
    ) {}

    /**
     * Get sidebar nav links for a tenant. If $user is provided, only links the user is allowed to see are returned.
     * Core modules are always included; normal modules come from the tenant_modules pivot.
     */
    public function navLinksForTenant(Tenant $tenant, ?Authenticatable $user = null): array
    {
        $links = [];
        $modulesConfig = config('modules', []);

        // Core modules are always "enabled" for the tenant (they may not be in the pivot)
        $coreKeys = collect($modulesConfig)
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && !empty($m['core']))
            ->keys()
            ->toArray();

        $enabledFromPivot = $this->registry->getEnabledModulesFromDb($tenant);
        $enabledModuleKeys = array_values(array_unique(array_merge($coreKeys, $enabledFromPivot)));

        $userIsAdmin = $user && method_exists($user, 'getRelation') && $user->relationLoaded('roles')
            ? $user->roles->contains('name', 'admin')
            : ($user && method_exists($user, 'roles') ? $user->roles()->where('name', 'admin')->exists() : false);

        // Build links from config for each enabled key (do not depend on Module table so sidebar works even if modules:sync is not run)
        foreach ($enabledModuleKeys as $moduleKey) {
            $config = $modulesConfig[$moduleKey] ?? null;
            if (!is_array($config) || empty($config['label']) || empty($config['route_name'])) {
                continue;
            }

            $routeName = $config['route_name'];
            if (!Route::has($routeName)) {
                continue;
            }

            $permission = $config['permission'] ?? null;
            if ($permission && $user && !$userIsAdmin && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
                continue;
            }

            $links[] = [
                'key' => $moduleKey,
                'label' => $config['label'],
                'route' => $routeName,
                'group' => $config['group'] ?? 'system',
                'icon' => $config['icon'] ?? 'cog',
                'permission' => $permission,
                'sidebar_order' => (int) ($config['sidebar_order'] ?? 100),
            ];
        }

        usort($links, function (array $a, array $b) {
            $order = ($a['sidebar_order'] ?? 100) <=> ($b['sidebar_order'] ?? 100);
            return $order !== 0 ? $order : strcmp($a['label'], $b['label']);
        });

        return $links;
    }
}
