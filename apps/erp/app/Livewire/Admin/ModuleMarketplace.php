<?php

namespace App\Livewire\Admin;

use App\Jobs\InstallModuleJob;
use App\Jobs\UninstallModuleJob;
use App\Models\Tenant;
use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ModuleMarketplace extends Component
{
    public $tenantId = null;
    public string $search = '';
    private const PENDING_TTL = 300;
    private const PENDING_PREFIX = 'module_pending:';

    public function mount(): void
    {
        $first = Tenant::orderBy('name')->first();
        $this->tenantId = $first?->id;
    }

    public function getTenantsProperty()
    {
        return Tenant::orderBy('name')->get(['id', 'name', 'code']);
    }

    public function getModulesListProperty(): array
    {
        $config = config('modules', []);
        $list = collect($config)
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && isset($m['label']))
            ->map(fn ($m, $key) => [
                'key' => $key,
                'label' => $m['label'] ?? $key,
                'description' => $m['description'] ?? '',
                'group' => $m['group'] ?? 'system',
                'core' => (bool) ($m['core'] ?? false),
            ])
            ->values()
            ->toArray();

        if ($this->search !== '') {
            $q = strtolower($this->search);
            $list = array_values(array_filter($list, fn ($m) =>
                str_contains(strtolower($m['label']), $q) || str_contains(strtolower($m['key']), $q)
            ));
        }

        return $list;
    }

    public function isPending(string $moduleKey): bool
    {
        if (!$this->tenantId) {
            return false;
        }
        return Cache::has(self::PENDING_PREFIX . "{$this->tenantId}:{$moduleKey}");
    }

    public function install(string $moduleKey): void
    {
        if (!$this->tenantId) {
            notify()->error('Sélectionnez un vendeur avant d\'installer un module.');
            return;
        }

        $pendingKey = self::PENDING_PREFIX . "{$this->tenantId}:{$moduleKey}";
        Cache::put($pendingKey, 'install', self::PENDING_TTL);

        try {
            InstallModuleJob::dispatchSync(
                (int) $this->tenantId,
                $moduleKey,
                auth()->id()
            );
            $this->clearTenantModuleCache();
            notify()->success("Module « {$moduleKey} » installé.");
        } catch (\Throwable $e) {
            Cache::forget($pendingKey);
            notify()->error($e->getMessage());
        }
    }

    public function uninstall(string $moduleKey): void
    {
        if (!$this->tenantId) {
            notify()->error('Sélectionnez un vendeur avant de désinstaller un module.');
            return;
        }

        $pendingKey = self::PENDING_PREFIX . "{$this->tenantId}:{$moduleKey}";
        Cache::put($pendingKey, 'uninstall', self::PENDING_TTL);

        try {
            UninstallModuleJob::dispatchSync(
                (int) $this->tenantId,
                $moduleKey,
                auth()->id()
            );
            $this->clearTenantModuleCache();
            notify()->success("Module « {$moduleKey} » désinstallé.");
        } catch (\Throwable $e) {
            Cache::forget($pendingKey);
            notify()->error($e->getMessage());
        }
    }

    /**
     * Install the module for all tenants (so it appears in sidebar for every vendeur).
     */
    public function installForAllTenants(string $moduleKey): void
    {
        $tenants = Tenant::orderBy('name')->get();
        foreach ($tenants as $t) {
            Cache::put(self::PENDING_PREFIX . "{$t->id}:{$moduleKey}", 'install', self::PENDING_TTL);
            InstallModuleJob::dispatchSync((int) $t->id, $moduleKey, auth()->id());
        }
        $this->clearTenantModuleCache();
        notify()->success('Module installé pour tous les vendeurs.');
    }

    private function clearTenantModuleCache(): void
    {
        if (!$this->tenantId) {
            return;
        }

        $tenant = Tenant::find($this->tenantId);
        if ($tenant) {
            app(ModuleRegistry::class)->clearCache($tenant);
        }
    }

    public function syncModules(): void
    {
        Artisan::call('modules:sync');
        notify()->success('Modules synchronisés.');
    }

    /** Clear stuck "En cours…" flags after a failed install (Redis cache). */
    public function clearStuckPending(): void
    {
        if (!$this->tenantId) {
            notify()->error('Sélectionnez un vendeur.');
            return;
        }

        foreach ($this->modulesList as $module) {
            Cache::forget(self::PENDING_PREFIX . "{$this->tenantId}:{$module['key']}");
        }

        notify()->success('État « en cours » réinitialisé. Réessayez Installer.');
    }

    public function render()
    {
        $enabledMap = [];
        $pendingMap = [];
        if ($this->tenantId) {
            $tenant = Tenant::find($this->tenantId);
            $enabledKeys = $tenant
                ? app(ModuleRegistry::class)->getEnabledModulesFromDb($tenant)
                : [];
            $enabledLookup = array_fill_keys($enabledKeys, true);

            foreach ($this->modulesList as $m) {
                $enabledMap[$m['key']] = !empty($m['core']) || isset($enabledLookup[$m['key']]);
                $pendingMap[$m['key']] = $this->isPending($m['key']);
            }
        }

        return view('livewire.admin.module-marketplace')
            ->layout('layouts.app', [
                'title' => 'Packages',
                'subtitle' => 'Installation / désinstallation des modules par vendeur',
            ])
            ->with([
                'tenants' => $this->tenants,
                'modulesList' => $this->modulesList,
                'enabledMap' => $enabledMap,
                'pendingMap' => $pendingMap,
            ]);
    }
}
