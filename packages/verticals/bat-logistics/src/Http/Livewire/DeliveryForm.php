<?php

namespace InovCom\Logistique\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Logistique\Models\Delivery;
use InovCom\Logistique\Models\DeliveryItem;
use InovCom\Logistique\Models\Driver;
use InovCom\Logistique\Models\Vehicle;
use InovCom\Logistique\Services\LogisticsService;
use InovCom\Stock\Models\Product;
use InovCom\Stock\Models\Warehouse;
use Livewire\Component;

class DeliveryForm extends Component
{
    use AuthorizesWithTenant;

    public ?int    $deliveryId          = null;
    public string  $vehicle_id          = '0';
    public string  $driver_id           = '0';
    public string  $source_warehouse_id = '0';
    public string  $project_id          = '';
    public string  $destination         = '';
    public string  $scheduled_at        = '';
    public string  $notes               = '';
    public array   $items               = [];

    public function mount(?Delivery $delivery = null): void
    {
        if ($delivery && $delivery->exists) {
            $this->tenantAuthorize('logistique.edit');

            if (!in_array($delivery->status, ['pending'], true)) {
                notify()->error(__('Seules les livraisons en attente peuvent être modifiées.'));
                $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
                $this->redirect(route('tenant.logistique.show', ['tenant' => $tenantCode, 'delivery' => $delivery->id]));
                return;
            }

            $this->deliveryId          = $delivery->id;
            $this->vehicle_id          = (string) $delivery->vehicle_id;
            $this->driver_id           = (string) $delivery->driver_id;
            $this->source_warehouse_id = (string) $delivery->source_warehouse_id;
            $this->project_id          = (string) ($delivery->project_id ?? '');
            $this->destination         = $delivery->destination ?? '';
            $this->scheduled_at        = $delivery->scheduled_at?->format('Y-m-d') ?? '';
            $this->notes               = $delivery->notes ?? '';

            foreach ($delivery->items as $item) {
                $this->items[] = [
                    'product_id' => (string) $item->product_id,
                    'quantity'   => (string) $item->quantity,
                ];
            }
        } else {
            $this->tenantAuthorize('logistique.create');
            if (request()->filled('project')) {
                $this->project_id = (string) request()->query('project');
            }
        }

        if (empty($this->items)) {
            $this->addItem();
        }
    }

    protected function rules(): array
    {
        return [
            'vehicle_id'          => ['required', 'integer', 'min:1'],
            'driver_id'           => ['required', 'integer', 'min:1'],
            'source_warehouse_id' => ['required', 'integer', 'min:1'],
            'destination'         => ['nullable', 'string', 'max:255'],
            'scheduled_at'        => ['required', 'date'],
            'notes'               => ['nullable', 'string'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'integer', 'min:1'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => '1'];
    }

    public function removeItem(int $index): void
    {
        array_splice($this->items, $index, 1);
        $this->items = array_values($this->items);
        if (empty($this->items)) {
            $this->addItem();
        }
    }

    public function save(LogisticsService $service): void
    {
        $this->tenantAuthorize($this->deliveryId ? 'logistique.edit' : 'logistique.create');
        $this->validate();

        $validItems = collect($this->items)
            ->filter(fn($r) => (int)($r['product_id'] ?? 0) > 0 && (float)($r['quantity'] ?? 0) > 0)
            ->map(fn($r) => ['product_id' => (int)$r['product_id'], 'quantity' => (float)$r['quantity']])
            ->values()
            ->toArray();

        if (empty($validItems)) {
            notify()->error(__('Ajoutez au moins un article à livrer.'));
            return;
        }

        $data = [
            'vehicle_id'          => (int) $this->vehicle_id,
            'driver_id'           => (int) $this->driver_id,
            'source_warehouse_id' => (int) $this->source_warehouse_id,
            'project_id'          => $this->project_id !== '' ? (int) $this->project_id : null,
            'destination'         => $this->destination ?: null,
            'scheduled_at'        => $this->scheduled_at,
            'notes'               => $this->notes ?: null,
        ];

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        if ($this->deliveryId) {
            $delivery = Delivery::on('tenant')->findOrFail($this->deliveryId);
            $delivery->update($data);
            $delivery->items()->delete();
            foreach ($validItems as $item) {
                DeliveryItem::on('tenant')->create(array_merge($item, ['delivery_id' => $delivery->id]));
            }
            notify()->success(__('Livraison mise à jour.'));
        } else {
            $delivery = $service->createDelivery($data, $validItems);
            notify()->success(__('Livraison créée.'));
        }

        $this->redirect(route('tenant.logistique.show', ['tenant' => $tenantCode, 'delivery' => $delivery->id]), navigate: true);
    }

    public function cancel(): void
    {
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        if ($this->deliveryId) {
            $this->redirect(route('tenant.logistique.show', ['tenant' => $tenantCode, 'delivery' => $this->deliveryId]), navigate: true);
        } else {
            $this->redirect(route('tenant.logistique.index', ['tenant' => $tenantCode]), navigate: true);
        }
    }

    public function render()
    {
        return view('inovcom-logistique::livewire.deliveries.form', [
            'vehicles'   => Vehicle::on('tenant')->ordered()->get(['id', 'name', 'plate_number', 'type', 'status']),
            'drivers'    => Driver::on('tenant')->active()->ordered()->get(['id', 'name', 'phone']),
            'warehouses' => Warehouse::on('tenant')->active()->ordered()->get(['id', 'name', 'location']),
            'products'   => Product::on('tenant')->active()->ordered()->get(['id', 'name', 'code', 'unit']),
            'isEdit'     => (bool) $this->deliveryId,
        ])->layout('layouts.app', [
            'title'    => $this->deliveryId ? 'Modifier la livraison' : 'Nouvelle livraison',
            'subtitle' => '',
        ]);
    }
}
