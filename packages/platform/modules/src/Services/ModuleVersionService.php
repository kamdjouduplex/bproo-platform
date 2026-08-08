<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Log;

class ModuleVersionService
{
    /**
     * Resolve composer package name candidates for a module key.
     *
     * @return list<string>
     */
    public function resolvePackageNames(string $moduleKey, ?string $storedPackageName = null): array
    {
        $names = [];
        if ($storedPackageName) {
            $names[] = $storedPackageName;
        }

        $cfgPackage = config("modules.{$moduleKey}.package_name");
        if (is_string($cfgPackage) && $cfgPackage !== '') {
            $names[] = $cfgPackage;
        }

        $names[] = "bproo/{$moduleKey}";
        $names[] = "inovcom/{$moduleKey}";

        // Shared packages (e.g. foreign_purchases → purchases)
        $handler = config("modules.{$moduleKey}.lifecycle_handler");
        if (is_string($handler) && preg_match('/\\\\([A-Za-z0-9]+)\\\\/', $handler, $m)) {
            $segment = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $m[1]) ?? $m[1]);
            $names[] = "bproo/{$segment}";
            $names[] = "inovcom/{$segment}";
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * Read version from a local package composer.json (path repo / @dev).
     */
    public function getLocalComposerVersion(string $packageName): ?string
    {
        $composer = $this->readPackageComposer($packageName);
        if (! is_array($composer)) {
            return null;
        }

        if (! empty($composer['version'])) {
            return (string) $composer['version'];
        }

        $pretty = $composer['extra']['branch-alias']['dev-main']
            ?? $composer['extra']['branch-alias']['dev-master']
            ?? null;

        return is_string($pretty) && $pretty !== '' ? rtrim($pretty, '-dev') : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readPackageComposer(string $packageName): ?array
    {
        foreach ($this->composerJsonPaths($packageName) as $path) {
            if (! is_file($path)) {
                continue;
            }
            $composer = json_decode((string) file_get_contents($path), true);
            if (is_array($composer)) {
                return $composer;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function composerJsonPaths(string $packageName): array
    {
        $key = $this->getModuleKeyFromPackage($packageName);
        $paths = [];

        if (InstalledVersions::isInstalled($packageName)) {
            try {
                $installPath = InstalledVersions::getInstallPath($packageName);
                if (is_string($installPath) && $installPath !== '') {
                    $paths[] = rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $repoRoot = dirname(base_path(), 2);
        $paths = array_merge($paths, [
            base_path("vendor/{$packageName}/composer.json"),
            base_path("vendor/inovcom/{$key}/composer.json"),
            base_path("vendor/bproo/{$key}/composer.json"),
            base_path("packages/inovcom/{$key}/composer.json"),
            base_path("packages/bproo/{$key}/composer.json"),
            "{$repoRoot}/packages/inovcom/{$key}/composer.json",
            "{$repoRoot}/packages/bproo/{$key}/composer.json",
            "{$repoRoot}/packages/verticals/{$key}/composer.json",
        ]);

        return array_values(array_unique($paths));
    }

    /**
     * Best-effort package version for UI / catalog.
     *
     * @return array{package_name: ?string, version: ?string, compatibility: ?array}
     */
    public function resolveModuleRelease(string $moduleKey, ?Module $module = null): array
    {
        $module ??= Module::where('key', $moduleKey)->first();
        $packageNames = $this->resolvePackageNames($moduleKey, $module?->package_name);

        foreach ($packageNames as $packageName) {
            $local = $this->getLocalComposerVersion($packageName);
            $installed = $this->getPackageVersion($packageName);
            $version = $local ?: $installed;
            if (! $version) {
                continue;
            }

            // Prefer explicit composer.json version over path-repo "dev-main".
            if ($local && $installed && str_starts_with($installed, 'dev-')) {
                $version = $local;
            }

            return [
                'package_name' => $this->canonicalPackageName($packageName) ?? $packageName,
                'version' => $version,
                'compatibility' => $this->getCompatibility($packageName) ?? $module?->compatibility,
            ];
        }

        return [
            'package_name' => $module?->package_name,
            'version' => $module?->version,
            'compatibility' => $module?->compatibility,
        ];
    }

    private function canonicalPackageName(string $packageName): ?string
    {
        $composer = $this->readPackageComposer($packageName);

        return is_string($composer['name'] ?? null) ? $composer['name'] : $packageName;
    }

    /**
     * Get version from composer.json
     */
    public function getPackageVersion(string $packageName): ?string
    {
        if (!InstalledVersions::isInstalled($packageName)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($packageName)
            ?: InstalledVersions::getVersion($packageName);
    }

    /**
     * Get compatibility requirements from composer.json extra.inovcom
     */
    public function getCompatibility(string $packageName): ?array
    {
        $composer = $this->readPackageComposer($packageName);
        if (! is_array($composer)) {
            return null;
        }

        return $composer['extra']['inovcom']['compatibility']
            ?? $composer['extra']['bproo']['compatibility']
            ?? null;
    }

    /**
     * Extract module key from package name (e.g., "inovcom/items" -> "items")
     */
    private function getModuleKeyFromPackage(string $packageName): string
    {
        return str_replace(['inovcom/', 'bproo/'], '', $packageName);
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

        $release = $this->resolveModuleRelease($moduleKey, $module);
        if (! $release['version'] && ! $release['package_name']) {
            return;
        }

        $module->update(array_filter([
            'version' => $release['version'],
            'compatibility' => $release['compatibility'],
            'package_name' => $release['package_name'],
        ], fn ($v) => $v !== null));
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
