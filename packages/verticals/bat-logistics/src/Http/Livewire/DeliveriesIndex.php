<?php

namespace InovCom\Logistique\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Logistique\Models\Delivery;
use InovCom\Logistique\Services\LogisticsService;
use Livewire\Component;
use Livewire\WithPagination;

class DeliveriesIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $search       = '';
    public string $statusFilter = '';
    public ?int   $projectFilter = null;

    public function mount(): void
    {
        $this->tenantAuthorize('logistique.view');
        if (request()->filled('project')) {
            $this->projectFilter = (int) request()->query('project');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function markInProgress(int $id, LogisticsService $service): void
    {
        $this->tenantAuthorize('logistique.edit');
        try {
            $service->markAsInProgress($id);
            notify()->success(__('Livraison démarrée.'));
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function markCompleted(int $id, LogisticsService $service): void
    {
        $this->tenantAuthorize('logistique.complete');
        try {
            $service->markAsCompleted($id, auth('tenant')->id());
            notify()->success(__('Livraison complétée. Stock déduit automatiquement.'));
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function cancel(int $id, LogisticsService $service): void
    {
        $this->tenantAuthorize('logistique.edit');
        try {
            $service->cancel($id);
            notify()->info(__('Livraison annulée.'));
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function render()
    {
        $deliveries = Delivery::on('tenant')
            ->with(['vehicle', 'driver', 'sourceWarehouse'])
            ->withCount('items')
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->search !== '', fn($q) => $q->where(function ($q) {
                $q->where('code', 'ilike', '%' . $this->search . '%')
                  ->orWhere('destination', 'ilike', '%' . $this->search . '%');
            }))
            ->when($this->statusFilter !== '', fn($q) => $q->where('status', $this->statusFilter))
            ->ordered()
            ->paginate(15);

        $stats = [
            'pending'     => Delivery::on('tenant')->where('status', 'pending')->count(),
            'in_progress' => Delivery::on('tenant')->where('status', 'in_progress')->count(),
            'completed'   => Delivery::on('tenant')->where('status', 'completed')->count(),
        ];

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        return view('inovcom-logistique::livewire.deliveries.index', [
            'deliveries' => $deliveries,
            'stats'      => $stats,
            'tenantCode' => $tenantCode,
            'canCreate'  => $this->tenantCan('logistique.create'),
            'canEdit'    => $this->tenantCan('logistique.edit'),
            'canComplete'=> $this->tenantCan('logistique.complete'),
        ])->layout('layouts.app', ['title' => 'Logistique', 'subtitle' => 'Livraisons']);
    }
}
