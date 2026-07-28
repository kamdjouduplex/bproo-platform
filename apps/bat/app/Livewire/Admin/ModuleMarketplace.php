<?php

namespace App\Livewire\Admin;

use App\Jobs\InstallModuleJob;
use App\Jobs\UninstallModuleJob;
use App\Models\Module;
use App\Models\Tenant;
use App\Services\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ModuleMarketplace extends Component
{
    public string $search = '';
    public ?int $tenantId = null;
    public array $tenants = [];
    public array $modules = [];
    public array $states = [];
    public array $pending = [];

    public function mount(): void
    {
        $this->tenants = Tenant::orderBy('name')->get(['id', 'name', 'code'])->toArray();
        $this->tenantId = $this->tenants[0]['id'] ?? null;
        $this->syncModulesFromConfig();
        $this->loadModules();
        $this->loadStates();
        $this->loadPending();
    }

    public function updatedSearch(): void
    {
        $this->loadModules();
    }

    public function updatedTenantId(): void
    {
        $this->loadStates();
        $this->loadPending();
    }

    public function syncModulesFromConfig(): void
    {
        Artisan::call('modules:sync');
    }

    public function install(string $moduleKey): void
    {
        if (!$this->tenantId) {
            session()->flash('error', __('Sélectionnez une entreprise.'));
            return;
        }

        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            session()->flash('error', 'Module introuvable.');
            return;
        }

        $key = "module_job:tenant:{$this->tenantId}:{$moduleKey}";
        if (Cache::has($key)) {
            session()->flash('info', 'Une action est déjà en cours pour ce module.');
            return;
        }

        Cache::put($key, 'install', now()->addMinutes(10));
        InstallModuleJob::dispatch($this->tenantId, $moduleKey);
        $this->loadPending();

        $tenant = Tenant::find($this->tenantId);
        $tenant?->modules()->syncWithoutDetaching([$module->id => ['enabled' => true]]);
        $this->loadStates();

        session()->flash('success', 'Installation mise en file d\'attente. Rafraîchissez dans quelques secondes.');
    }

    public function uninstall(string $moduleKey): void
    {
        if (!$this->tenantId) {
            session()->flash('error', __('Sélectionnez une entreprise.'));
            return;
        }

        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            session()->flash('error', 'Module introuvable.');
            return;
        }

        $key = "module_job:tenant:{$this->tenantId}:{$moduleKey}";
        if (Cache::has($key)) {
            session()->flash('info', 'Une action est déjà en cours pour ce module.');
            return;
        }

        Cache::put($key, 'uninstall', now()->addMinutes(10));
        UninstallModuleJob::dispatch($this->tenantId, $moduleKey);
        $this->loadPending();

        session()->flash('success', 'Désinstallation mise en file d\'attente. Rafraîchissez dans quelques secondes.');
    }

    public function isPending(string $moduleKey): bool
    {
        return ($this->pending[$moduleKey] ?? false);
    }

    public function getModuleConfig(string $key): array
    {
        $config = config('modules', []);
        if (!is_array($config)) {
            return [];
        }
        $module = $config[$key] ?? null;
        return is_array($module) ? $module : [];
    }

    private function loadModules(): void
    {
        $query = Module::orderBy('label');
        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('label', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('key', 'like', $term);
            });
        }
        $this->modules = $query->get()->toArray();
    }

    private function loadStates(): void
    {
        $this->states = [];
        if (!$this->tenantId) {
            return;
        }

        $tenant = Tenant::with('modules')->find($this->tenantId);
        if (!$tenant) {
            return;
        }

        $pivot = $tenant->modules->keyBy('id');
        foreach ($this->modules as $module) {
            $id = $module['id'];
            $p = $pivot->get($id)?->pivot;
            $this->states[$module['key']] = $p ? (bool) $p->enabled : (bool) ($module['enabled_by_default'] ?? false);
        }
    }

    private function loadPending(): void
    {
        $this->pending = [];
        if (!$this->tenantId) {
            return;
        }
        foreach ($this->modules as $module) {
            $key = $module['key'];
            $this->pending[$key] = Cache::has("module_job:tenant:{$this->tenantId}:{$key}");
        }
    }

    public function render()
    {
        $this->loadStates();
        $this->loadPending();

        return view('livewire.admin.module-marketplace')
            ->layout('layouts.app', [
                'title' => 'Packages',
                'subtitle' => 'Marketplace des modules',
            ]);
    }
}
