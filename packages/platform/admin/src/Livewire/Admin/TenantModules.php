<?php

namespace App\Livewire\Admin;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\ModuleRegistry;
use Livewire\Component;

class TenantModules extends Component
{
    public array $tenants = [];
    public array $modules = [];
    public $tenantId = null;
    public array $states = [];
    public array $updatedAt = [];
    public array $moduleKeyById = [];

    public function mount(): void
    {
        $this->tenants = Tenant::orderBy('name')->get(['id', 'name', 'code'])->toArray();
        $this->modules = Module::orderBy('label')->get([
            'id',
            'key',
            'label',
            'description',
            'enabled_by_default',
        ])->toArray();
        $this->moduleKeyById = collect($this->modules)->pluck('key', 'id')->toArray();

        $this->tenantId = $this->tenants[0]['id'] ?? null;
        $this->loadStates();
    }

    public function updatedTenantId(): void
    {
        $this->loadStates();
    }

    public function toggle(int $moduleId): void
    {
        if (!$this->tenantId) {
            return;
        }

        $enabled = !($this->states[$moduleId] ?? false);
        $this->states[$moduleId] = $enabled;

        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            return;
        }

        $moduleKey = $this->moduleKeyById[$moduleId] ?? null;
        if ($moduleKey) {
            $registry = app(ModuleRegistry::class);
            if ($enabled) {
                try {
                    $registry->ensureNoFamilyConflict($moduleKey, $tenant);
                } catch (\RuntimeException $e) {
                    $this->states[$moduleId] = false;
                    notify()->error($e->getMessage());
                    return;
                }
                try {
                    $registry->install($moduleKey, $tenant);
                } catch (\Throwable $e) {
                    $this->states[$moduleId] = false;
                    $tenant->modules()->syncWithoutDetaching([$moduleId => ['enabled' => false]]);
                    notify()->error($e->getMessage());
                    return;
                }
            } else {
                $registry->uninstall($moduleKey, $tenant);
            }
        }

        $tenant->modules()->syncWithoutDetaching([
            $moduleId => ['enabled' => $enabled],
        ]);

        $this->loadStates();
    }

    private function loadStates(): void
    {
        $this->states = [];
        if (!$this->tenantId) {
            return;
        }

        $tenant = Tenant::with('modules')->find($this->tenantId);
        $moduleMap = $tenant?->modules->keyBy('id') ?? collect();

        foreach ($this->modules as $module) {
            $moduleId = $module['id'];
            $pivot = $moduleMap->get($moduleId)?->pivot;
            $enabled = $pivot ? (bool) $pivot->enabled : (bool) $module['enabled_by_default'];
            $this->states[$moduleId] = $enabled;
            $this->updatedAt[$moduleId] = $pivot?->updated_at?->toDateTimeString();
        }
    }

    public function render()
    {
        return view('livewire.admin.tenant-modules')
            ->layout('layouts.app', [
                'title' => 'Modules',
                'subtitle' => 'Activation par vendeur',
            ]);
    }
}
