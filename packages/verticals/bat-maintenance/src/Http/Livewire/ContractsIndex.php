<?php

namespace InovCom\Maintenance\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Maintenance\Models\MaintenanceContract;
use Livewire\Component;
use Livewire\WithPagination;

class ContractsIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $search  = '';
    public string $status  = '';
    public int    $perPage = 10;

    public function mount(): void
    {
        $this->tenantAuthorize('maintenance.view');
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('maintenance.delete');
        $contract = MaintenanceContract::on('tenant')->findOrFail($id);
        $contract->delete();
        notify()->success(__('Contrat supprimé.'));
    }

    public function render()
    {
        $contracts = MaintenanceContract::on('tenant')
            ->with('client')
            ->when($this->search !== '', fn ($q) =>
                $q->where(fn ($q) =>
                    $q->where('code', 'like', "%{$this->search}%")
                      ->orWhere('title', 'like', "%{$this->search}%")
                )
            )
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->ordered()
            ->paginate($this->perPage);

        return view('inovcom-maintenance::livewire.contracts.index', compact('contracts'))
            ->layout('layouts.app', [
                'title'    => __('Contrats de maintenance'),
                'subtitle' => __('Contrats SLA, engagements de service.'),
            ]);
    }
}
