<?php

namespace App\Livewire\Admin;

use App\Models\ModuleEvent;
use App\Models\Tenant;
use Livewire\Component;
use Livewire\WithPagination;

class ModuleEvents extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;
    public ?int $tenantId = null;
    public ?string $moduleKey = null;
    public ?string $action = null;

    public function mount(): void
    {
        if (request()->filled('moduleKey')) {
            $this->moduleKey = (string) request()->query('moduleKey');
        }
        if (request()->filled('tenantId')) {
            $this->tenantId = (int) request()->query('tenantId');
        }
        if (request()->filled('action')) {
            $this->action = (string) request()->query('action');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTenantId(): void
    {
        $this->resetPage();
    }

    public function updatedModuleKey(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $events = ModuleEvent::query()
            ->with(['tenant', 'performer'])
            ->when($this->tenantId, fn ($q) => $q->where('tenant_id', $this->tenantId))
            ->when($this->moduleKey, fn ($q) => $q->where('module_key', $this->moduleKey))
            ->when($this->action, fn ($q) => $q->where('action', $this->action))
            ->when($this->search !== '', function ($q) {
                $q->where('module_key', 'like', '%' . $this->search . '%');
            })
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        return view('livewire.admin.module-events')
            ->layout('layouts.app', [
                'title' => 'Module Events',
                'subtitle' => 'Historique des activations',
            ])
            ->with([
                'events' => $events,
                'tenants' => Tenant::orderBy('name')->get(['id', 'name', 'code']),
            ]);
    }
}
