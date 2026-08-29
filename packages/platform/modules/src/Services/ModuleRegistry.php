<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleEvent;
use App\Models\Tenant;
use App\Services\ModuleVersionService;
use InovCom\Kernel\Contracts\ModuleLifecycle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ModuleRegistry
{
    /**
     * Cache duration in seconds (1 hour)
     */
    private const CACHE_DURATION = 3600;

    /**
     * Check if cache driver supports tags
     */
    private function supportsTags(): bool
    {
        $store = Cache::getStore();
        return method_exists($store, 'tags');
    }

    /**
     * Get cache instance with or without tags
     */
    private function getCacheStore(array $tags = []): \Illuminate\Contracts\Cache\Repository
    {
        if (!empty($tags) && $this->supportsTags()) {
            return Cache::tags($tags);
        }
        return Cache::store();
    }

    /**
     * Check if a module is enabled for a tenant (with caching)
     */
    public function isEnabled(string $moduleKey, ?Tenant $tenant = null): bool
    {
        // In system context (no tenant), check if it's a core module
        if (!$tenant) {
            $module = Module::where('key', $moduleKey)->first();
            return $module?->core ?? false;
        }

        $modules = config('modules', []);
        if (! empty($modules[$moduleKey]['core'] ?? false)) {
            return true;
        }

        $cacheKey = $this->getCacheKey($moduleKey, $tenant->id);
        $cached = Cache::get($cacheKey);
        if ($cached === true) {
            return true;
        }

        $enabled = $this->moduleEnabledInDb($tenant, $moduleKey);

        // Never cache a negative result: Control Center and ERP do not share file cache,
        // so a visit before activation would keep returning 403 for an hour.
        if ($enabled) {
            $this->getCacheStore($this->getCacheTags($tenant->id))
                ->put($cacheKey, true, self::CACHE_DURATION);
        } else {
            Cache::forget($cacheKey);
        }

        return $enabled;
    }

    /**
     * Get all enabled module keys for a tenant (with caching)
     */
    public function getEnabledModules(Tenant $tenant): array
    {
        $cacheKey = "tenant:{$tenant->id}:modules:enabled:all";
        $tags = $this->getCacheTags($tenant->id);

        return $this->getCacheStore($tags)
            ->remember($cacheKey, self::CACHE_DURATION, function () use ($tenant) {
                return $this->getEnabledModulesFromDb($tenant);
            });
    }

    /**
     * Get enabled module keys for a tenant from DB (no cache). Used for sidebar so it always reflects current state.
     * Always reads the landlord connection — never the tenant database.
     */
    public function getEnabledModulesFromDb(Tenant $tenant): array
    {
        $central = $this->centralConnectionName($tenant);

        return DB::connection($central)
            ->table('tenant_modules')
            ->join('modules', 'modules.id', '=', 'tenant_modules.module_id')
            ->where('tenant_modules.tenant_id', $tenant->id)
            ->where('tenant_modules.enabled', true)
            ->pluck('modules.key')
            ->map(fn ($key) => (string) $key)
            ->all();
    }

    private function moduleEnabledInDb(Tenant $tenant, string $moduleKey): bool
    {
        $central = $this->centralConnectionName($tenant);

        return DB::connection($central)
            ->table('tenant_modules')
            ->join('modules', 'modules.id', '=', 'tenant_modules.module_id')
            ->where('tenant_modules.tenant_id', $tenant->id)
            ->where('tenant_modules.enabled', true)
            ->where('modules.key', $moduleKey)
            ->exists();
    }

    /**
     * Clear module cache for a tenant
     */
    public function clearCache(?Tenant $tenant = null): void
    {
        if ($this->supportsTags()) {
            // Use tags for efficient clearing
            if ($tenant) {
                Cache::tags($this->getCacheTags($tenant->id))->flush();
            } else {
                Cache::tags(['modules'])->flush();
            }
        } else {
            // Without tags, clear specific keys or all cache
            if ($tenant) {
                // Clear known cache keys for this tenant
                $this->clearTenantCacheKeys($tenant->id);
            } else {
                // Clear all cache (less efficient but works)
                Cache::flush();
            }
        }
    }

    /**
     * Clear specific cache keys for a tenant (when tags not supported)
     */
    private function clearTenantCacheKeys(int $tenantId): void
    {
        // Get all module keys from config
        $moduleKeys = array_keys(config('modules', []));
        
        // Clear individual module cache keys
        foreach ($moduleKeys as $moduleKey) {
            Cache::forget($this->getCacheKey($moduleKey, $tenantId));
        }
        
        // Clear the "all modules" cache key
        Cache::forget("tenant:{$tenantId}:modules:enabled:all");
    }

    /**
     * Get cache key for a module
     */
    private function getCacheKey(string $moduleKey, int $tenantId): string
    {
        return "tenant:{$tenantId}:module:{$moduleKey}:enabled";
    }

    /**
     * Get cache tags for a tenant
     */
    private function getCacheTags(int $tenantId): array
    {
        return ['modules', "tenant:{$tenantId}"];
    }

    public function handlerFor(string $moduleKey): ?ModuleLifecycle
    {
        $module = Module::where('key', $moduleKey)->first();
        $class = $module?->lifecycle_handler;

        if (!$class || !class_exists($class)) {
            return null;
        }

        $handler = app($class);

        return $handler instanceof ModuleLifecycle ? $handler : null;
    }

    /**
     * Get module_family for a module key from config.
     */
    public function getModuleFamily(string $moduleKey): ?string
    {
        $config = config("modules.{$moduleKey}", []);
        return is_array($config) ? ($config['module_family'] ?? null) : null;
    }

    /**
     * Get all module keys that belong to the same family (excluding the given key).
     */
    public function getOtherModulesInFamily(string $moduleKey): array
    {
        $family = $this->getModuleFamily($moduleKey);
        if (!$family) {
            return [];
        }
        $modules = config('modules', []);
        $keys = [];
        foreach ($modules as $key => $cfg) {
            if ($key === $moduleKey || !is_array($cfg)) {
                continue;
            }
            if (($cfg['module_family'] ?? null) === $family) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Ensure at most one module per family is enabled. Throws if enabling would violate that.
     */
    public function ensureNoFamilyConflict(string $moduleKey, Tenant $tenant): void
    {
        $others = $this->getOtherModulesInFamily($moduleKey);
        foreach ($others as $otherKey) {
            if ($this->isEnabled($otherKey, $tenant)) {
                $modules = config('modules', []);
                $otherLabel = $modules[$otherKey]['label'] ?? $otherKey;
                $thisLabel = $modules[$moduleKey]['label'] ?? $moduleKey;
                throw new \RuntimeException(
                    "Un seul module de vente peut être activé. Désactivez d'abord « {$otherLabel} » pour activer « {$thisLabel} »."
                );
            }
        }
    }

    public function install(string $moduleKey, Tenant $tenant, ?int $performedBy = null): void
    {
        $central = $this->centralConnectionName($tenant);
        $tenant->setConnection($central);

        try {
            // Always set tenant connection before any tenant DB operations (admin activation has no tenant context)
            $this->ensureTenantConnection($tenant);

            // Only one module per family per tenant (e.g. sales vs sales-restaurant)
            $this->ensureNoFamilyConflict($moduleKey, $tenant);

            // Check compatibility before installation
            $versionService = app(ModuleVersionService::class);
            if (!$versionService->checkCompatibility($moduleKey)) {
                $module = Module::on($central)->where('key', $moduleKey)->first();
                $compatibility = $module?->compatibility ?? [];
                throw new \Exception(
                    "Module '{$moduleKey}' is not compatible with current core version. " .
                    "Required: {$compatibility['min_core_version']} - {$compatibility['max_core_version']}"
                );
            }

            $this->runModuleMigrations($moduleKey, $tenant);

            $handler = $this->handlerFor($moduleKey);
            if ($handler) {
                $handler->install($tenant);
            }

            // Track version
            $module = Module::on($central)->where('key', $moduleKey)->first();
            if ($module && $module->version) {
                $versionService->updateTenantModuleVersion($tenant, $moduleKey, $module->version);
            }

            ModuleEvent::on($central)->create([
                'tenant_id' => $tenant->id,
                'module_key' => $moduleKey,
                'action' => 'install',
                'performed_by' => $performedBy ?? Auth::guard('web')->id(),
            ]);

            // Clear cache after installation
            $this->clearCache($tenant);
        } finally {
            $this->restoreCentralConnection($central, $tenant);
        }
    }

    public function uninstall(string $moduleKey, Tenant $tenant, ?int $performedBy = null): void
    {
        $this->ensureTenantConnection($tenant);

        $handler = $this->handlerFor($moduleKey);
        if ($handler) {
            $handler->uninstall($tenant);
        }

        ModuleEvent::create([
            'tenant_id' => $tenant->id,
            'module_key' => $moduleKey,
            'action' => 'uninstall',
            'performed_by' => $performedBy ?? Auth::guard('web')->id(),
        ]);

        // Clear cache after uninstallation
        $this->clearCache($tenant);
    }

    /**
     * Ensure tenant database connection is configured (e.g. when activating from admin without tenant context).
     */
    private function ensureTenantConnection(Tenant $tenant): void
    {
        config(['database.connections.tenant' => $tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * For non-core modules with migration_tag: publish migrations and run them
     * against the tenant's database. No-op for core modules or those without a tag.
     */
    public function runModuleMigrations(string $moduleKey, Tenant $tenant): void
    {
        $modules = config('modules', []);
        $module = $modules[$moduleKey] ?? null;
        if (!is_array($module) || !empty($module['core'] ?? false)) {
            return;
        }

        $tag = $module['migration_tag'] ?? null;
        if (!$tag) {
            return;
        }

        Artisan::call('vendor:publish', ['--tag' => $tag, '--force' => true]);
        $publishOutput = Artisan::output();
        if (str_contains($publishOutput, 'Unable to locate publishable resources')) {
            throw new \RuntimeException(
                "Migration tag [{$tag}] is not available on this host for module [{$moduleKey}]. ".
                'Provision this tenant on the product app that owns the package (ERP / Pharma / Pressing).'
            );
        }

        $this->ensureTenantConnection($tenant);

        $path = database_path('migrations/tenant_modules');
        if (! is_dir($path)) {
            throw new \RuntimeException(
                "No migrations published for tag [{$tag}] (module [{$moduleKey}]). ".
                'Check that the vertical package is installed and its ServiceProvider registers the tag.'
            );
        }

        $central = $this->centralConnectionName($tenant);
        $exit = 1;
        try {
            $exit = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant_modules',
                '--force' => true,
            ]);
        } finally {
            $this->restoreCentralConnection($central, $tenant);
        }
        if ($exit !== 0) {
            throw new \RuntimeException(
                "Migration failed for module [{$moduleKey}] (tag [{$tag}]): ".trim(Artisan::output())
            );
        }
    }

    /**
     * Landlord connection that holds tenants / modules. migrate --database=tenant
     * temporarily switches the default connection and does not restore it if it throws.
     */
    private function centralConnectionName(Tenant $tenant): string
    {
        $name = $tenant->getConnectionName();
        if (is_string($name) && $name !== '' && $name !== 'tenant') {
            return $name;
        }

        $current = DB::getDefaultConnection();
        if (is_string($current) && $current !== '' && $current !== 'tenant') {
            return $current;
        }

        $snapshot = config('database.central');
        if (is_string($snapshot) && $snapshot !== '' && $snapshot !== 'tenant') {
            return $snapshot;
        }

        return 'pgsql';
    }

    private function restoreCentralConnection(string $central, ?Tenant $tenant = null): void
    {
        DB::setDefaultConnection($central);
        config(['database.default' => $central]);
        if ($tenant) {
            $tenant->setConnection($central);
        }
    }
}
