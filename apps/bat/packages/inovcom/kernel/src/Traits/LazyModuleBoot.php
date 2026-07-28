<?php

namespace InovCom\Kernel\Traits;

use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\App;

trait LazyModuleBoot
{
    /**
     * Check if the module should be booted
     * Returns true if:
     * - We're in system context (admin routes)
     * - Module is a core module
     * - Module is enabled for current tenant
     * 
     * Note: Service providers using this trait MUST define:
     * protected string $moduleKey = 'your-module';
     */
    protected function shouldBootModule(): bool
    {
        // Get module key from the service provider
        // Service providers MUST define $moduleKey property
        if (!isset($this->moduleKey) || empty($this->moduleKey)) {
            return true; // Backward compatibility
        }
        
        $moduleKey = $this->moduleKey;

        // Always boot in system context (admin routes)
        if (request()->is('admin/*') || !App::bound('tenant')) {
            return true;
        }

        // Check if module is enabled for current tenant
        $tenant = App::make('tenant');
        if (!$tenant) {
            return true; // System context
        }

        $registry = App::make(ModuleRegistry::class);
        
        // Check if it's a core module (always enabled)
        $modules = config('modules', []);
        $moduleConfig = $modules[$moduleKey] ?? null;
        
        if ($moduleConfig && ($moduleConfig['core'] ?? false)) {
            return true;
        }

        // Check if module is enabled for this tenant
        return $registry->isEnabled($moduleKey, $tenant);
    }
}
