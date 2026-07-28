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

        $cacheKey = $this->getCacheKey($moduleKey, $tenant->id);
        $tags = $this->getCacheTags($tenant->id);

        return $this->getCacheStore($tags)
            ->remember($cacheKey, self::CACHE_DURATION, function () use ($moduleKey, $tenant) {
                return $tenant->modules()
                    ->where('modules.key', $moduleKey)
                    ->where('tenant_modules.enabled', true)
                    ->exists();
            });
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
                return $tenant->modules()
                    ->where('tenant_modules.enabled', true)
                    ->pluck('modules.key')
                    ->toArray();
            });
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

    public function install(string $moduleKey, Tenant $tenant): void
    {
        // Check compatibility before installation
        $versionService = app(ModuleVersionService::class);
        if (!$versionService->checkCompatibility($moduleKey)) {
            $module = Module::where('key', $moduleKey)->first();
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
        $module = Module::where('key', $moduleKey)->first();
        if ($module && $module->version) {
            $versionService->updateTenantModuleVersion($tenant, $moduleKey, $module->version);
        }

        ModuleEvent::create([
            'tenant_id' => $tenant->id,
            'module_key' => $moduleKey,
            'action' => 'install',
            'performed_by' => Auth::guard('web')->id(),
        ]);

        // Clear cache after installation
        $this->clearCache($tenant);
    }

    public function uninstall(string $moduleKey, Tenant $tenant): void
    {
        $handler = $this->handlerFor($moduleKey);
        if ($handler) {
            $handler->uninstall($tenant);
        }

        ModuleEvent::create([
            'tenant_id' => $tenant->id,
            'module_key' => $moduleKey,
            'action' => 'uninstall',
            'performed_by' => Auth::guard('web')->id(),
        ]);

        // Clear cache after uninstallation
        $this->clearCache($tenant);
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

        config(['database.connections.tenant' => $tenant->databaseConfig()]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        $path = database_path('migrations/tenant_modules');
        if (!is_dir($path)) {
            return;
        }
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant_modules',
            '--force' => true,
        ]);
    }
}
