<?php

namespace App\Livewire\Admin;

use App\Jobs\InstallModuleJob;
use App\Jobs\UninstallModuleJob;
use App\Models\Tenant;
use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Activation — enable/install modules per client (tenant).
 * Catalog & platform-wide ops live under Modules + Module fiche.
 */
class TenantModules extends Component
{
    public $tenantId = null;

    public string $search = '';

    public string $group = '';

    public string $status = ''; // '' | active | inactive

    private const PENDING_TTL = 300;

    private const PENDING_PREFIX = 'module_pending:';

    public function mount(): void
    {
        $requested = request()->query('tenant');
        if ($requested && Tenant::whereKey($requested)->exists()) {
            $this->tenantId = (int) $requested;
        } else {
            $this->tenantId = Tenant::orderBy('name')->value('id');
        }
    }

    public function getTenantsProperty()
    {
        return Tenant::orderBy('name')->get(['id', 'name', 'code', 'type']);
    }

    public function install(string $moduleKey): void
    {
        if (! $this->tenantId) {
            notify()->error('Sélectionnez un client.');

            return;
        }

        if ((bool) (config("modules.{$moduleKey}.core") ?? false)) {
            notify()->info('Module core déjà disponible.');

            return;
        }

        $pendingKey = self::PENDING_PREFIX."{$this->tenantId}:{$moduleKey}";
        Cache::put($pendingKey, 'install', self::PENDING_TTL);

        try {
            InstallModuleJob::dispatchSync((int) $this->tenantId, $moduleKey, auth()->id());
            $this->clearTenantCache();
            notify()->success('Module activé.');
        } catch (\Throwable $e) {
            Cache::forget($pendingKey);
            notify()->error($e->getMessage());
        }
    }

    public function uninstall(string $moduleKey): void
    {
        if (! $this->tenantId) {
            notify()->error('Sélectionnez un client.');

            return;
        }

        if ((bool) (config("modules.{$moduleKey}.core") ?? false)) {
            notify()->error('Impossible de désactiver un module core.');

            return;
        }

        $pendingKey = self::PENDING_PREFIX."{$this->tenantId}:{$moduleKey}";
        Cache::put($pendingKey, 'uninstall', self::PENDING_TTL);

        try {
            UninstallModuleJob::dispatchSync((int) $this->tenantId, $moduleKey, auth()->id());
            $this->clearTenantCache();
            notify()->success('Module désactivé.');
        } catch (\Throwable $e) {
            Cache::forget($pendingKey);
            notify()->error($e->getMessage());
        }
    }

    public function activateDefaults(): void
    {
        if (! $this->tenantId) {
            notify()->error('Sélectionnez un client.');

            return;
        }

        $n = 0;
        foreach ($this->catalog() as $module) {
            if ($module['core'] || ! $module['enabled_by_default'] || $module['enabled']) {
                continue;
            }
            try {
                Cache::put(self::PENDING_PREFIX."{$this->tenantId}:{$module['key']}", 'install', self::PENDING_TTL);
                InstallModuleJob::dispatchSync((int) $this->tenantId, $module['key'], auth()->id());
                $n++;
            } catch (\Throwable $e) {
                Cache::forget(self::PENDING_PREFIX."{$this->tenantId}:{$module['key']}");
            }
        }

        $this->clearTenantCache();
        notify()->success($n > 0 ? "{$n} module(s) par défaut activé(s)." : 'Rien à activer.');
    }

    public function clearStuckPending(): void
    {
        if (! $this->tenantId) {
            notify()->error('Sélectionnez un client.');

            return;
        }

        foreach ($this->catalog() as $module) {
            Cache::forget(self::PENDING_PREFIX."{$this->tenantId}:{$module['key']}");
        }

        notify()->success('États « en cours » réinitialisés.');
    }

    private function clearTenantCache(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant) {
            app(ModuleRegistry::class)->clearCache($tenant);
        }
    }

    private function catalog(): array
    {
        $groupLabels = config('modules.sidebar_groups', []);
        $config = collect(config('modules', []))
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && isset($m['label']));

        $enabledLookup = [];
        if ($this->tenantId) {
            $tenant = Tenant::find($this->tenantId);
            if ($tenant) {
                $enabledLookup = array_fill_keys(
                    app(ModuleRegistry::class)->getEnabledModulesFromDb($tenant),
                    true
                );
            }
        }

        $list = [];
        $tenant = $this->tenantId ? Tenant::find($this->tenantId) : null;
        $tenantType = $tenant ? Tenant::normalizeType($tenant->getRawOriginal('type') ?? $tenant->type) : null;

        foreach ($config as $key => $cfg) {
            $types = array_values(array_unique(array_map(
                static fn ($t) => Tenant::normalizeType($t),
                (array) ($cfg['tenant_types'] ?? [])
            )));
            if ($tenantType && $types !== [] && ! in_array($tenantType, $types, true)) {
                continue;
            }

            $core = (bool) ($cfg['core'] ?? false);
            $enabled = $core || isset($enabledLookup[$key]);
            $pending = $this->tenantId
                ? Cache::has(self::PENDING_PREFIX."{$this->tenantId}:{$key}")
                : false;

            $list[] = [
                'key' => $key,
                'label' => $cfg['label'] ?? $key,
                'description' => $cfg['description'] ?? '',
                'group' => $cfg['group'] ?? 'system',
                'group_label' => $groupLabels[$cfg['group'] ?? 'system'] ?? ($cfg['group'] ?? 'system'),
                'core' => $core,
                'enabled_by_default' => (bool) ($cfg['enabled_by_default'] ?? false),
                'module_family' => $cfg['module_family'] ?? null,
                'enabled' => $enabled,
                'pending' => $pending,
            ];
        }

        return $list;
    }

    public function render()
    {
        $catalog = collect($this->catalog());

        if ($this->search !== '') {
            $q = mb_strtolower(trim($this->search));
            $catalog = $catalog->filter(fn ($m) => str_contains(mb_strtolower($m['label']), $q)
                || str_contains(mb_strtolower($m['key']), $q));
        }
        if ($this->group !== '') {
            $catalog = $catalog->where('group', $this->group);
        }
        if ($this->status === 'active') {
            $catalog = $catalog->where('enabled', true);
        } elseif ($this->status === 'inactive') {
            $catalog = $catalog->where('enabled', false);
        }

        $catalog = $catalog->sortBy('label')->values();
        $full = collect($this->catalog());

        $groups = collect(config('modules', []))
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && isset($m['label']))
            ->pluck('group')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn ($g) => [$g => config("modules.sidebar_groups.{$g}", $g)]);

        $tenant = $this->tenantId ? Tenant::find($this->tenantId) : null;

        return view('livewire.admin.tenant-modules', [
            'tenants' => $this->tenants,
            'modules' => $catalog,
            'groups' => $groups,
            'tenant' => $tenant,
            'kpis' => [
                'active' => $full->where('enabled', true)->count(),
                'inactive' => $full->where('enabled', false)->count(),
                'core' => $full->where('core', true)->count(),
                'total' => $full->count(),
            ],
        ])->layout('layouts.app', [
            'title' => 'Activation',
            'subtitle' => 'Modules par client',
        ]);
    }
}
