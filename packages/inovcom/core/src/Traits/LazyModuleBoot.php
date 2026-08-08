<?php

namespace InovCom\Kernel\Traits;

use Illuminate\Support\Facades\App;

trait LazyModuleBoot
{
    /**
     * Check if the module should be booted (register routes, views, etc.).
     *
     * Service providers using this trait MUST define: protected string $moduleKey = 'your-module';
     *
     * Returns true when:
     * - $moduleKey is not set (backward compatibility)
     * - Running in system/admin context (no tenant bound)
     * - Module is a core module (always enabled)
     * - Module is enabled for the current tenant
     *
     * Falls back to always-boot when ModuleRegistry is unavailable
     * (e.g. ERP where routes must exist for sidebar links).
     */
    protected function shouldBootModule(): bool
    {
        if (!isset($this->moduleKey) || empty($this->moduleKey)) {
            return true;
        }

        if (request()->is('admin/*') || !App::bound('tenant')) {
            return true;
        }

        $tenant = App::make('tenant');
        if (!$tenant) {
            return true;
        }

        if (!App::bound(\App\Services\ModuleRegistry::class)) {
            return true;
        }

        $registry = App::make(\App\Services\ModuleRegistry::class);

        $modules = config('modules', []);
        $moduleConfig = $modules[$this->moduleKey] ?? null;

        if ($moduleConfig && ($moduleConfig['core'] ?? false)) {
            return true;
        }

        if ($registry->isEnabled($this->moduleKey, $tenant)) {
            return true;
        }

        foreach ($this->alsoBootWhenModules ?? [] as $altKey) {
            if (is_string($altKey) && $altKey !== '' && $registry->isEnabled($altKey, $tenant)) {
                return true;
            }
        }

        return false;
    }
}
