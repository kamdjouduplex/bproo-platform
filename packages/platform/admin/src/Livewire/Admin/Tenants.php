<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use Livewire\Component;

class Tenants extends Component
{
    public array $tenants = [];

    public function mount(): void
    {
        $this->refreshTenants();
    }

    public function delete(int $tenantId): void
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return;
        }

        $tenant->delete();
        $this->refreshTenants();
    }

    private function refreshTenants(): void
    {
        $this->tenants = Tenant::orderBy('name')->get()->toArray();
    }

    public function render()
    {
        return view('livewire.admin.tenants')
            ->layout('layouts.app', [
                'title' => 'Vendeurs',
                'subtitle' => 'Gestion des boutiques',
            ]);
    }
}
