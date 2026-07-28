<?php

namespace InovCom\Maintenance\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Maintenance\Models\MaintenanceOrder;
use Livewire\Component;
use Livewire\WithPagination;

class OrdersIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $search   = '';
    public string $status   = '';
    public string $priority = '';
    public int    $perPage  = 10;

    public function mount(): void
    {
        $this->tenantAuthorize('maintenance.view');
    }

    public function updatedSearch(): void   { $this->resetPage(); }
    public function updatedStatus(): void   { $this->resetPage(); }
    public function updatedPriority(): void { $this->resetPage(); }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('maintenance.delete');
        $order = MaintenanceOrder::on('tenant')->findOrFail($id);
        $order->delete();
        notify()->success(__('Ordre supprimé.'));
    }

    public function render()
    {
        $orders = MaintenanceOrder::on('tenant')
            ->with(['client', 'contract', 'assignedUser'])
            ->when($this->search !== '', fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('code', 'like', "%{$this->search}%")
                      ->orWhere('title', 'like', "%{$this->search}%")
                      ->orWhere('reported_by', 'like', "%{$this->search}%")
                )
            )
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->priority !== '', fn ($q) => $q->where('priority', $this->priority))
            ->ordered()
            ->paginate($this->perPage);

        return view('inovcom-maintenance::livewire.orders.index', compact('orders'))
            ->layout('layouts.app', [
                'title'    => __('Ordres de maintenance'),
                'subtitle' => __('Demandes correctives, préventives et urgences.'),
            ]);
    }
}
