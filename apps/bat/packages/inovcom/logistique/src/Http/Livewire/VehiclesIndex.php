<?php

namespace InovCom\Logistique\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Logistique\Models\Delivery;
use InovCom\Logistique\Models\Vehicle;
use Livewire\Component;

class VehiclesIndex extends Component
{
    use AuthorizesWithTenant;

    public string $name         = '';
    public string $plate_number = '';
    public string $type         = 'truck';
    public string $capacity_kg  = '';
    public string $status       = 'available';
    public string $notes        = '';

    public bool $showModal   = false;
    public ?int $editVehicleId = null;

    public function mount(): void
    {
        $this->tenantAuthorize('logistique.view');
    }

    public function openCreate(): void
    {
        $this->tenantAuthorize('logistique.create');
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->tenantAuthorize('logistique.edit');
        $v = Vehicle::on('tenant')->findOrFail($id);
        $this->editVehicleId = $id;
        $this->name         = $v->name;
        $this->plate_number = $v->plate_number;
        $this->type         = $v->type;
        $this->capacity_kg  = (string) ($v->capacity_kg ?? '');
        $this->status       = $v->status;
        $this->notes        = $v->notes ?? '';
        $this->showModal    = true;
    }

    public function closeModal(): void
    {
        $this->showModal     = false;
        $this->editVehicleId = null;
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'plate_number' => ['required', 'string', 'max:50'],
            'type'         => ['required', 'string'],
            'capacity_kg'  => ['nullable', 'numeric', 'min:0'],
            'status'       => ['required', 'string'],
        ];
    }

    public function save(): void
    {
        $this->tenantAuthorize($this->editVehicleId ? 'logistique.edit' : 'logistique.create');
        $this->validate();

        $data = [
            'name'         => $this->name,
            'plate_number' => $this->plate_number,
            'type'         => $this->type,
            'capacity_kg'  => $this->capacity_kg !== '' ? (float) $this->capacity_kg : null,
            'status'       => $this->status,
            'notes'        => $this->notes ?: null,
        ];

        if ($this->editVehicleId) {
            Vehicle::on('tenant')->findOrFail($this->editVehicleId)->update($data);
            notify()->success(__('Véhicule mis à jour.'));
        } else {
            Vehicle::on('tenant')->create($data);
            notify()->success(__('Véhicule ajouté.'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('logistique.delete');
        if (Delivery::on('tenant')->where('vehicle_id', $id)->exists()) {
            notify()->error(__('Ce véhicule est lié à des livraisons.'));
            return;
        }
        Vehicle::on('tenant')->findOrFail($id)->delete();
        notify()->success(__('Véhicule supprimé.'));
    }

    private function resetForm(): void
    {
        $this->name         = '';
        $this->plate_number = '';
        $this->type         = 'truck';
        $this->capacity_kg  = '';
        $this->status       = 'available';
        $this->notes        = '';
    }

    public function render()
    {
        $vehicles   = Vehicle::on('tenant')->orderBy('name')->get();
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        return view('inovcom-logistique::livewire.vehicles.index', [
            'vehicles'   => $vehicles,
            'types'      => Vehicle::types(),
            'statuses'   => Vehicle::statuses(),
            'tenantCode' => $tenantCode,
            'canCreate'  => $this->tenantCan('logistique.create'),
            'canEdit'    => $this->tenantCan('logistique.edit'),
            'canDelete'  => $this->tenantCan('logistique.delete'),
        ])->layout('layouts.app', ['title' => 'Logistique', 'subtitle' => 'Véhicules']);
    }
}
