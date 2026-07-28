<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Log;

class ModuleVersionService
{
    /**
     * Get version from composer.json
     */
    public function getPackageVersion(string $packageName): ?string
    {
        if (!InstalledVersions::isInstalled($packageName)) {
            return null;
        }

        return InstalledVersions::getVersion($packageName);
    }

    /**
     * Get compatibility requirements from composer.json extra.inovcom
     */
    public function getCompatibility(string $packageName): ?array
    {
        if (!InstalledVersions::isInstalled($packageName)) {
            return null;
        }

        $packagePath = base_path("packages/inovcom/{$this->getModuleKeyFromPackage($packageName)}/composer.json");
        
        if (!file_exists($packagePath)) {
            return null;
        }

        $composer = json_decode(file_get_contents($packagePath), true);
        
        return $composer['extra']['inovcom']['compatibility'] ?? null;
    }

    /**
     * Extract module key from package name (e.g., "inovcom/items" -> "items")
     */
    private function getModuleKeyFromPackage(string $packageName): string
    {
        return str_replace('inovcom/', '', $packageName);
    }

    /**
     * Sync module version from package
     */
    public function syncModuleVersion(string $moduleKey): void
    {
        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            return;
        }

        $packageName = $module->package_name ?? "inovcom/{$moduleKey}";
        $version = $this->getPackageVersion($packageName);
        $compatibility = $this->getCompatibility($packageName);

        if ($version) {
            $module->update([
                'version' => $version,
                'compatibility' => $compatibility,
                'package_name' => $packageName,
            ]);
        }
    }

    /**
     * Sync all module versions
     */
    public function syncAllVersions(): void
    {
        $modules = Module::all();
        
        foreach ($modules as $module) {
            try {
                $this->syncModuleVersion($module->key);
            } catch (\Exception $e) {
                Log::warning("Failed to sync version for module {$module->key}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if module version is compatible with core version
     */
    public function checkCompatibility(string $moduleKey, ?string $coreVersion = null): bool
    {
        $module = Module::where('key', $moduleKey)->first();
        if (!$module || !$module->compatibility) {
            return true; // No compatibility requirements = compatible
        }

        $coreVersion = $coreVersion ?? config('app.version', '1.0.0');
        $compatibility = $module->compatibility;

        $minVersion = $compatibility['min_core_version'] ?? null;
        $maxVersion = $compatibility['max_core_version'] ?? null;

        if ($minVersion && version_compare($coreVersion, $minVersion, '<')) {
            return false;
        }

        if ($maxVersion && version_compare($coreVersion, $maxVersion, '>')) {
            return false;
        }

        return true;
    }

    /**
     * Update tenant module version
     */
    public function updateTenantModuleVersion(Tenant $tenant, string $moduleKey, string $version): void
    {
        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            return;
        }

        $tenant->modules()->updateExistingPivot($module->id, [
            'installed_version' => $version,
            'installed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get installed version for tenant module
     */
    public function getInstalledVersion(Tenant $tenant, string $moduleKey): ?string
    {
        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            return null;
        }

        $pivot = $tenant->modules()
            ->where('modules.key', $moduleKey)
            ->first()?->pivot;

        return $pivot->installed_version ?? null;
    }

    /**
     * Check if module needs update
     */
    public function needsUpdate(Tenant $tenant, string $moduleKey): bool
    {
        $module = Module::where('key', $moduleKey)->first();
        if (!$module || !$module->version) {
            return false;
        }

        $installedVersion = $this->getInstalledVersion($tenant, $moduleKey);
        
        if (!$installedVersion) {
            return true; // Not installed yet
        }

        return version_compare($module->version, $installedVersion, '>');
    }

    /**
     * Get available updates for a tenant
     */
    public function getAvailableUpdates(Tenant $tenant): array
    {
        $updates = [];
        
        $enabledModules = $tenant->modules()
            ->where('tenant_modules.enabled', true)
            ->get();

        foreach ($enabledModules as $module) {
            if ($this->needsUpdate($tenant, $module->key)) {
                $updates[] = [
                    'module' => $module->key,
                    'module_label' => $module->label,
                    'current_version' => $this->getInstalledVersion($tenant, $module->key),
                    'available_version' => $module->version,
                ];
            }
        }

        return $updates;
    }
}
