<?php

namespace InovCom\Logistique\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Logistique\Models\Delivery;
use InovCom\Logistique\Services\LogisticsService;
use Livewire\Component;

class DeliveryShow extends Component
{
    use AuthorizesWithTenant;

    public Delivery $delivery;

    public function mount(Delivery $delivery): void
    {
        $this->tenantAuthorize('logistique.view');
        $this->delivery = $delivery->loadMissing(['vehicle', 'driver', 'sourceWarehouse', 'items.product']);
    }

    public function markInProgress(LogisticsService $service): void
    {
        $this->tenantAuthorize('logistique.edit');
        try {
            $service->markAsInProgress($this->delivery->id);
            $this->delivery->refresh()->loadMissing(['vehicle', 'driver', 'sourceWarehouse', 'items.product']);
            notify()->success(__('Livraison démarrée.'));
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function markCompleted(LogisticsService $service): void
    {
        $this->tenantAuthorize('logistique.complete');
        try {
            $service->markAsCompleted($this->delivery->id, auth('tenant')->id());
            $this->delivery->refresh()->loadMissing(['vehicle', 'driver', 'sourceWarehouse', 'items.product']);
            notify()->success(__('Livraison complétée. Le stock a été déduit automatiquement.'));
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function cancelDelivery(LogisticsService $service): void
    {
        $this->tenantAuthorize('logistique.edit');
        try {
            $service->cancel($this->delivery->id);
            $this->delivery->refresh();
            notify()->info(__('Livraison annulée.'));
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function render()
    {
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        return view('inovcom-logistique::livewire.deliveries.show', [
            'tenantCode'  => $tenantCode,
            'canEdit'     => $this->tenantCan('logistique.edit'),
            'canComplete' => $this->tenantCan('logistique.complete'),
        ])->layout('layouts.app', [
            'title'    => 'Livraison',
            'subtitle' => $this->delivery->code,
        ]);
    }
}
