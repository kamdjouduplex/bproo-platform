<?php

namespace InovCom\Kernel\Traits;

trait LazyModuleBoot
{
    /**
     * Check if the module should be booted (register routes, views, etc.).
     * We always boot so that routes exist for the sidebar; access is enforced by middleware (module:*).
     *
     * Service providers using this trait MUST define: protected string $moduleKey = 'your-module';
     */
    protected function shouldBootModule(): bool
    {
        if (!isset($this->moduleKey) || empty($this->moduleKey)) {
            return true;
        }
        // Always boot so routes are registered (sidebar needs Route::has() to show links).
        // Tenant access is enforced by EnsureModuleEnabled / module:* middleware.
        return true;
    }
}
