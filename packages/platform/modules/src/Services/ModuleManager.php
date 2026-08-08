<?php

namespace App\Services;

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
     * Supports optional `sidebar_children` for nested menus (CRM → Prospects / Opportunités / Activités).
     */
    public function navLinksForTenant(Tenant $tenant, ?Authenticatable $user = null): array
    {
        $links = [];
        $modulesConfig = config('modules', []);

        $coreKeys = collect($modulesConfig)
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && ! empty($m['core']))
            ->keys()
            ->toArray();

        $enabledFromPivot = $this->registry->getEnabledModulesFromDb($tenant);
        $enabledModuleKeys = array_values(array_unique(array_merge($coreKeys, $enabledFromPivot)));

        $userIsAdmin = $user && method_exists($user, 'getRelation') && $user->relationLoaded('roles')
            ? $user->roles->contains('name', 'admin')
            : ($user && method_exists($user, 'roles') ? $user->roles()->where('name', 'admin')->exists() : false);

        foreach ($enabledModuleKeys as $moduleKey) {
            $config = $modulesConfig[$moduleKey] ?? null;
            if (! is_array($config) || empty($config['label'])) {
                continue;
            }

            $hideWhen = $config['hide_when_modules'] ?? [];
            if (is_array($hideWhen) && $hideWhen !== [] && count(array_intersect($hideWhen, $enabledModuleKeys)) > 0) {
                continue;
            }

            if (array_key_exists('sidebar', $config) && $config['sidebar'] === false) {
                continue;
            }

            $children = $this->buildChildren($config['sidebar_children'] ?? [], $user, $userIsAdmin);
            $hasConfiguredChildren = ! empty($config['sidebar_children']);
            $menuOnly = ! empty($config['menu_only']);

            $routeName = $config['route_name'] ?? null;
            $hasRoute = ! $menuOnly && is_string($routeName) && $routeName !== '' && Route::has($routeName);

            if (! $hasRoute && $children === []) {
                continue;
            }

            $permission = $config['permission'] ?? null;
            $canParent = $userIsAdmin
                || ! $permission
                || ! $user
                || (method_exists($user, 'hasPermission') && $this->userHasAnyPermission($user, array_merge(
                    [$permission],
                    (array) ($config['permission_any'] ?? [])
                )));

            if ($hasConfiguredChildren) {
                // Nested: show if any child visible, or parent permission with at least a route
                if ($children === [] && ! $canParent) {
                    continue;
                }
                if ($children === [] && $canParent && ! $hasRoute) {
                    continue;
                }
            } elseif (! $canParent) {
                continue;
            }

            $links[] = [
                'key' => $moduleKey,
                'label' => $config['label'],
                // Parent with children (ex. CRM) is a toggle only — never inherit a child route.
                'route' => $hasRoute ? $routeName : null,
                'group' => $config['group'] ?? 'system',
                'icon' => $config['icon'] ?? 'cog',
                'permission' => $permission,
                'sidebar_order' => (int) ($config['sidebar_order'] ?? 100),
                'children' => $children,
            ];
        }

        usort($links, function (array $a, array $b) {
            $order = ($a['sidebar_order'] ?? 100) <=> ($b['sidebar_order'] ?? 100);

            return $order !== 0 ? $order : strcmp($a['label'], $b['label']);
        });

        return $links;
    }

    /**
     * @param  list<array<string, mixed>>  $childrenConfig
     * @return list<array<string, mixed>>
     */
    private function buildChildren(array $childrenConfig, ?Authenticatable $user, bool $userIsAdmin): array
    {
        $children = [];

        foreach ($childrenConfig as $child) {
            if (! is_array($child) || empty($child['label']) || empty($child['route'])) {
                continue;
            }
            if (! Route::has($child['route'])) {
                continue;
            }

            $perms = array_values(array_filter(array_merge(
                [(string) ($child['permission'] ?? '')],
                (array) ($child['permission_any'] ?? [])
            )));

            if ($perms !== [] && $user && ! $userIsAdmin && method_exists($user, 'hasPermission')) {
                if (! $this->userHasAnyPermission($user, $perms)) {
                    continue;
                }
            }

            $children[] = [
                'key' => $child['key'] ?? $child['route'],
                'label' => $child['label'],
                'route' => $child['route'],
                'icon' => $child['icon'] ?? 'cog',
                'permission' => $child['permission'] ?? null,
            ];
        }

        return $children;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userHasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($permission !== '' && $user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
