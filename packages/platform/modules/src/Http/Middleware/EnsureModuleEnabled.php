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

        if (!$tenant) {
            abort(403, 'Tenant context required.');
        }

        // Use cached ModuleRegistry for better performance
        $registry = app(ModuleRegistry::class);
        if (!$registry->isEnabled($moduleKey, $tenant)) {
            abort(403, 'Module not enabled for this tenant.');
        }

        // Enforce user permission for this module (sidebar + direct URL access)
        $permission = config("modules.{$moduleKey}.permission");
        if ($permission) {
            $user = auth('tenant')->user();
            if (!$user) {
                abort(403, 'Authentication required.');
            }
            if (!$user->hasPermission($permission)) {
                abort(403, 'You do not have permission to access this section.');
            }
        }

        return $next($request);
    }
}
