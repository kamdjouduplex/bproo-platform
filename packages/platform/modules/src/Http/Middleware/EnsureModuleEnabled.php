<?php

namespace App\Http\Middleware;

use App\Services\ModuleRegistry;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleKey)
    {
        $tenant = app(TenantManager::class)->tenant();

        if (! $tenant) {
            abort(403, 'Tenant context required.');
        }

        $registry = app(ModuleRegistry::class);
        if (! $this->moduleAccessible($registry, $moduleKey, $tenant)) {
            abort(403, 'Module not enabled for this tenant.');
        }

        $permission = config("modules.{$moduleKey}.permission");
        $permissionAny = (array) config("modules.{$moduleKey}.permission_any", []);

        if ($permission || $permissionAny !== []) {
            $user = auth('tenant')->user();
            if (! $user) {
                abort(403, 'Authentication required.');
            }

            $isAdmin = method_exists($user, 'roles')
                && $user->roles()->where('name', 'admin')->exists();

            if (! $isAdmin && method_exists($user, 'hasPermission')) {
                $allowed = false;
                if ($permission && $user->hasPermission($permission)) {
                    $allowed = true;
                }
                foreach ($permissionAny as $alt) {
                    if (is_string($alt) && $alt !== '' && $user->hasPermission($alt)) {
                        $allowed = true;
                        break;
                    }
                }
                if (! $allowed) {
                    abort(403, 'You do not have permission to access this section.');
                }
            }
        }

        return $next($request);
    }

    private function moduleAccessible(ModuleRegistry $registry, string $moduleKey, $tenant): bool
    {
        if ($registry->isEnabled($moduleKey, $tenant)) {
            return true;
        }

        // e.g. prospects routes allowed when CRM suite is enabled
        foreach ((array) config("modules.{$moduleKey}.enabled_when_modules", []) as $alt) {
            if (is_string($alt) && $alt !== '' && $registry->isEnabled($alt, $tenant)) {
                return true;
            }
        }

        return false;
    }
}
