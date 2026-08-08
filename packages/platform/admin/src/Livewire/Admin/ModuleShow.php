<?php

namespace App\Livewire\Admin;

use App\Jobs\InstallModuleJob;
use App\Jobs\UninstallModuleJob;
use App\Models\Module;
use App\Models\ModuleEvent;
use App\Models\Tenant;
use App\Services\ModuleRegistry;
use App\Services\ModulesCatalogSync;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Module fiche — full platform brain view of one package + ops actions.
 */
class ModuleShow extends Component
{
    use WithPagination;

    public string $moduleKey = '';

    public string $tenantSearch = '';

    protected $paginationTheme = 'cc';

    private const PENDING_TTL = 300;

    private const PENDING_PREFIX = 'module_pending:';

    public function mount(string $moduleKey): void
    {
        $cfg = config("modules.{$moduleKey}");
        if (! is_array($cfg) || ! isset($cfg['label'])) {
            abort(404);
        }
        $this->moduleKey = $moduleKey;
    }

    public function updatingTenantSearch(): void
    {
        $this->resetPage();
    }

    public function syncCatalog(): void
    {
        Artisan::call('modules:sync');
        ModulesCatalogSync::syncFromConfig(preserveDefaults: true);
        notify()->success('Catalogue synchronisé.');
    }

    public function installForAll(): void
    {
        if ($this->isCore()) {
            notify()->info('Module core : déjà disponible pour tous.');

            return;
        }

        $n = 0;
        foreach (Tenant::orderBy('name')->get(['id']) as $tenant) {
            Cache::put(self::PENDING_PREFIX."{$tenant->id}:{$this->moduleKey}", 'install', self::PENDING_TTL);
            try {
                InstallModuleJob::dispatchSync((int) $tenant->id, $this->moduleKey, auth()->id());
                $n++;
            } catch (\Throwable $e) {
                Cache::forget(self::PENDING_PREFIX."{$tenant->id}:{$this->moduleKey}");
            }
        }

        notify()->success("Installé pour {$n} client(s).");
    }

    public function uninstallForAll(): void
    {
        if ($this->isCore()) {
            notify()->error('Impossible de désinstaller un module core.');

            return;
        }

        $n = 0;
        foreach (Tenant::orderBy('name')->get(['id']) as $tenant) {
            Cache::put(self::PENDING_PREFIX."{$tenant->id}:{$this->moduleKey}", 'uninstall', self::PENDING_TTL);
            try {
                UninstallModuleJob::dispatchSync((int) $tenant->id, $this->moduleKey, auth()->id());
                $n++;
            } catch (\Throwable $e) {
                Cache::forget(self::PENDING_PREFIX."{$tenant->id}:{$this->moduleKey}");
            }
        }

        notify()->success("Désinstallé pour {$n} client(s).");
    }

    public function installForTenant(int $tenantId): void
    {
        if ($this->isCore()) {
            notify()->info('Module core.');

            return;
        }

        $pendingKey = self::PENDING_PREFIX."{$tenantId}:{$this->moduleKey}";
        Cache::put($pendingKey, 'install', self::PENDING_TTL);

        try {
            InstallModuleJob::dispatchSync($tenantId, $this->moduleKey, auth()->id());
            notify()->success('Module installé pour ce client.');
        } catch (\Throwable $e) {
            Cache::forget($pendingKey);
            notify()->error($e->getMessage());
        }
    }

    public function uninstallForTenant(int $tenantId): void
    {
        if ($this->isCore()) {
            notify()->error('Impossible de désinstaller un module core.');

            return;
        }

        $pendingKey = self::PENDING_PREFIX."{$tenantId}:{$this->moduleKey}";
        Cache::put($pendingKey, 'uninstall', self::PENDING_TTL);

        try {
            UninstallModuleJob::dispatchSync($tenantId, $this->moduleKey, auth()->id());
            notify()->success('Module désinstallé pour ce client.');
        } catch (\Throwable $e) {
            Cache::forget($pendingKey);
            notify()->error($e->getMessage());
        }
    }

    public function clearStuckPending(): void
    {
        foreach (Tenant::pluck('id') as $tenantId) {
            Cache::forget(self::PENDING_PREFIX."{$tenantId}:{$this->moduleKey}");
        }
        notify()->success('États « en cours » réinitialisés pour ce module.');
    }

    private function isCore(): bool
    {
        return (bool) (config("modules.{$this->moduleKey}.core") ?? false);
    }

    private function meta(): array
    {
        $cfg = config("modules.{$this->moduleKey}", []);
        $db = Module::where('key', $this->moduleKey)->first();
        $group = $cfg['group'] ?? 'system';
        $groupLabels = config('modules.sidebar_groups', []);
        $release = app(\App\Services\ModuleVersionService::class)->resolveModuleRelease($this->moduleKey, $db);

        if ($db && ($release['version'] || $release['package_name'])) {
            $db->fill(array_filter([
                'version' => $release['version'],
                'package_name' => $release['package_name'],
                'compatibility' => $release['compatibility'],
            ], fn ($v) => $v !== null));
            if ($db->isDirty()) {
                $db->save();
            }
        }

        return [
            'key' => $this->moduleKey,
            'label' => $cfg['label'] ?? $this->moduleKey,
            'description' => $cfg['description'] ?? $db?->description,
            'group' => $group,
            'group_label' => $groupLabels[$group] ?? $group,
            'core' => (bool) ($cfg['core'] ?? false),
            'enabled_by_default' => (bool) ($cfg['enabled_by_default'] ?? $db?->enabled_by_default ?? false),
            'route_name' => $cfg['route_name'] ?? $db?->route_name,
            'lifecycle_handler' => $cfg['lifecycle_handler'] ?? $db?->lifecycle_handler,
            'migration_tag' => $cfg['migration_tag'] ?? null,
            'permission' => $cfg['permission'] ?? null,
            'module_family' => $cfg['module_family'] ?? null,
            'tenant_types' => $cfg['tenant_types'] ?? [],
            'icon' => $cfg['icon'] ?? null,
            'version' => $release['version'] ?: ($db?->version ?: ($cfg['version'] ?? null)),
            'installed_version' => $db?->installed_version,
            'package_name' => $release['package_name'] ?: ($db?->package_name ?: ($cfg['package_name'] ?? null)),
            'compatibility' => $release['compatibility'] ?? $db?->compatibility ?? ($cfg['compatibility'] ?? null),
            'db' => $db,
            'family_siblings' => app(ModuleRegistry::class)->getOtherModulesInFamily($this->moduleKey),
        ];
    }

    public function render()
    {
        $meta = $this->meta();
        $moduleId = $meta['db']?->id;

        $activeCount = 0;
        if ($moduleId) {
            $activeCount = DB::table('tenant_modules')
                ->where('module_id', $moduleId)
                ->where('enabled', true)
                ->count();
        }

        $tenantsQuery = Tenant::query()->orderBy('name');
        if (trim($this->tenantSearch) !== '') {
            $term = '%'.mb_strtolower(trim($this->tenantSearch)).'%';
            $tenantsQuery->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
            });
        }

        $tenants = $tenantsQuery->paginate(15);
        $enabledLookup = [];
        if ($moduleId) {
            $enabledLookup = DB::table('tenant_modules')
                ->where('module_id', $moduleId)
                ->where('enabled', true)
                ->whereIn('tenant_id', $tenants->getCollection()->pluck('id'))
                ->pluck('tenant_id')
                ->flip()
                ->all();
        }

        $events = ModuleEvent::query()
            ->with(['tenant', 'performer'])
            ->where('module_key', $this->moduleKey)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return view('livewire.admin.module-show', [
            'meta' => $meta,
            'activeCount' => $activeCount,
            'tenantTotal' => Tenant::count(),
            'tenants' => $tenants,
            'enabledLookup' => $enabledLookup,
            'events' => $events,
            'typeLabels' => config('tenant_types.types', []),
        ])->layout('layouts.app', [
            'title' => $meta['label'],
            'subtitle' => 'Fiche module',
        ]);
    }
}
