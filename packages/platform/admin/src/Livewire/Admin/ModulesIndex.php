<?php

namespace App\Livewire\Admin;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\ModulesCatalogSync;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Platform module catalog — Control Center brain view of available packages.
 * Per-tenant enablement lives in Activation (TenantModules).
 */
class ModulesIndex extends Component
{
    public string $search = '';

    public string $group = '';

    public string $type = ''; // '' | core | optional

    public function syncCatalog(): void
    {
        Artisan::call('modules:sync');
        ModulesCatalogSync::syncFromConfig(preserveDefaults: true);
        notify()->success('Catalogue modules synchronisé.');
    }

    public function render()
    {
        $config = collect(config('modules', []))
            ->except(['core_migration_tags', 'sidebar_groups'])
            ->filter(fn ($m) => is_array($m) && isset($m['label']));

        $groupLabels = config('modules.sidebar_groups', []);
        $dbModules = Module::query()->get()->keyBy('key');

        $enabledCounts = DB::table('tenant_modules')
            ->selectRaw('module_id, COUNT(*) as cnt')
            ->where('enabled', true)
            ->groupBy('module_id')
            ->pluck('cnt', 'module_id');

        $tenantTotal = Tenant::count();

        $versionService = app(\App\Services\ModuleVersionService::class);

        $rows = $config->map(function (array $cfg, string $key) use ($dbModules, $enabledCounts, $groupLabels, $versionService) {
            $db = $dbModules->get($key);
            $core = (bool) ($cfg['core'] ?? false);
            $release = $versionService->resolveModuleRelease($key, $db);

            return [
                'key' => $key,
                'label' => $cfg['label'] ?? $key,
                'description' => $cfg['description'] ?? ($db?->description ?? ''),
                'group' => $cfg['group'] ?? 'system',
                'group_label' => $groupLabels[$cfg['group'] ?? 'system'] ?? ($cfg['group'] ?? 'system'),
                'core' => $core,
                'enabled_by_default' => (bool) ($cfg['enabled_by_default'] ?? $db?->enabled_by_default ?? false),
                'version' => $release['version'] ?: ($db?->version ?: ($cfg['version'] ?? null)),
                'package_name' => $release['package_name'] ?: ($db?->package_name ?: ($cfg['package_name'] ?? null)),
                'module_family' => $cfg['module_family'] ?? null,
                'active_tenants' => $db ? (int) ($enabledCounts[$db->id] ?? 0) : 0,
                'in_db' => (bool) $db,
            ];
        })->values();

        if ($this->search !== '') {
            $q = mb_strtolower(trim($this->search));
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['label']), $q)
                || str_contains(mb_strtolower($r['key']), $q)
                || str_contains(mb_strtolower((string) $r['description']), $q));
        }
        if ($this->group !== '') {
            $rows = $rows->where('group', $this->group);
        }
        if ($this->type === 'core') {
            $rows = $rows->where('core', true);
        } elseif ($this->type === 'optional') {
            $rows = $rows->where('core', false);
        }

        $rows = $rows->sortBy('label')->values();

        $groups = $config->pluck('group')->filter()->unique()->sort()->values()
            ->mapWithKeys(fn ($g) => [$g => $groupLabels[$g] ?? $g]);

        return view('livewire.admin.modules-index', [
            'modules' => $rows,
            'groups' => $groups,
            'tenantTotal' => $tenantTotal,
            'kpis' => [
                'total' => $config->count(),
                'core' => $config->filter(fn ($m) => ! empty($m['core']))->count(),
                'optional' => $config->filter(fn ($m) => empty($m['core']))->count(),
                'tenants' => $tenantTotal,
            ],
        ])->layout('layouts.app', [
            'title' => 'Modules',
            'subtitle' => 'Catalogue plateforme',
        ]);
    }
}
