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

        return $next($request);
    }
}
