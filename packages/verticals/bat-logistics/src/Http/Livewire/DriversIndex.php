<?php

namespace InovCom\Logistique\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Logistique\Models\Delivery;
use InovCom\Logistique\Models\Driver;
use Livewire\Component;

class DriversIndex extends Component
{
    use AuthorizesWithTenant;

    public string $name           = '';
    public string $phone          = '';
    public string $email          = '';
    public string $license_number = '';
    public string $status         = 'active';
    public string $notes          = '';

    public bool $showModal    = false;
    public ?int $editDriverId = null;

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
        $d = Driver::on('tenant')->findOrFail($id);
        $this->editDriverId   = $id;
        $this->name           = $d->name;
        $this->phone          = $d->phone ?? '';
        $this->email          = $d->email ?? '';
        $this->license_number = $d->license_number ?? '';
        $this->status         = $d->status;
        $this->notes          = $d->notes ?? '';
        $this->showModal      = true;
    }

    public function closeModal(): void
    {
        $this->showModal    = false;
        $this->editDriverId = null;
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'status'         => ['required', 'string'],
        ];
    }

    public function save(): void
    {
        $this->tenantAuthorize($this->editDriverId ? 'logistique.edit' : 'logistique.create');
        $this->validate();

        $data = [
            'name'           => $this->name,
            'phone'          => $this->phone ?: null,
            'email'          => $this->email ?: null,
            'license_number' => $this->license_number ?: null,
            'status'         => $this->status,
            'notes'          => $this->notes ?: null,
        ];

        if ($this->editDriverId) {
            Driver::on('tenant')->findOrFail($this->editDriverId)->update($data);
            notify()->success(__('Chauffeur mis à jour.'));
        } else {
            Driver::on('tenant')->create($data);
            notify()->success(__('Chauffeur ajouté.'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('logistique.delete');
        if (Delivery::on('tenant')->where('driver_id', $id)->exists()) {
            notify()->error(__('Ce chauffeur est lié à des livraisons.'));
            return;
        }
        Driver::on('tenant')->findOrFail($id)->delete();
        notify()->success(__('Chauffeur supprimé.'));
    }

    private function resetForm(): void
    {
        $this->name           = '';
        $this->phone          = '';
        $this->email          = '';
        $this->license_number = '';
        $this->status         = 'active';
        $this->notes          = '';
    }

    public function render()
    {
        $drivers    = Driver::on('tenant')->orderBy('name')->get();
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        return view('inovcom-logistique::livewire.drivers.index', [
            'drivers'    => $drivers,
            'tenantCode' => $tenantCode,
            'canCreate'  => $this->tenantCan('logistique.create'),
            'canEdit'    => $this->tenantCan('logistique.edit'),
            'canDelete'  => $this->tenantCan('logistique.delete'),
        ])->layout('layouts.app', ['title' => 'Logistique', 'subtitle' => 'Chauffeurs']);
    }
}
