<?php

namespace App\Livewire\Admin;

use App\Jobs\SetupTenantMultiStoreJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Bus;
use Livewire\Component;

class TenantSettings extends Component
{
    public Tenant $tenant;

    public string $currency = 'XOF';
    public string $locale = 'fr';
    public string $timezone = 'Africa/Abidjan';
    public string $tax_rate = '0';
    public string $invoice_prefix = 'INV';
    public bool $multi_store_enabled = false;
    public string $default_store_name = 'Magasin principal';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->currency = (string) $tenant->getSetting('currency', 'XOF');
        $this->locale = (string) $tenant->getSetting('locale', 'fr');
        $this->timezone = (string) $tenant->getSetting('timezone', 'Africa/Abidjan');
        $this->tax_rate = (string) $tenant->getSetting('tax_rate', '0');
        $this->invoice_prefix = (string) $tenant->getSetting('invoice_prefix', 'INV');
        $this->multi_store_enabled = (bool) $tenant->multi_store_enabled;
    }

    public function save(): void
    {
        $data = $this->validate([
            'currency' => 'required|string|max:10',
            'locale' => 'required|string|max:10',
            'timezone' => 'required|string|max:64',
            'tax_rate' => 'required|numeric|min:0',
            'invoice_prefix' => 'required|string|max:20',
            'multi_store_enabled' => 'boolean',
        ]);

        $this->tenant->setSetting('currency', $data['currency']);
        $this->tenant->setSetting('locale', $data['locale']);
        $this->tenant->setSetting('timezone', $data['timezone']);
        $this->tenant->setSetting('tax_rate', $data['tax_rate']);
        $this->tenant->setSetting('invoice_prefix', $data['invoice_prefix']);
        $this->tenant->update([
            'multi_store_enabled' => $data['multi_store_enabled'],
            'multi_store_setup_status' => $data['multi_store_enabled']
                ? ($this->tenant->multi_store_setup_status ?: 'pending')
                : 'disabled',
            'multi_store_enabled_at' => $data['multi_store_enabled'] ? ($this->tenant->multi_store_enabled_at ?: now()) : null,
        ]);
    }

    public function enableMultiStore(): void
    {
        if ($this->tenant->multi_store_enabled && $this->tenant->multi_store_setup_status === 'completed') {
            notify()->success('Multi-magasins déjà activé pour ce tenant.');
            return;
        }

        $this->validate([
            'default_store_name' => 'required|string|max:255',
        ]);

        $this->tenant->update([
            'multi_store_enabled' => true,
            'multi_store_enabled_at' => now(),
            'multi_store_setup_status' => 'pending',
            'multi_store_setup_error' => null,
        ]);

        Bus::batch([
            new SetupTenantMultiStoreJob($this->tenant, $this->default_store_name),
        ])->name("multi-store-enable:{$this->tenant->code}")->dispatch();

        $this->multi_store_enabled = true;
        notify()->success('Activation multi-magasins lancée en batch.');
    }

    public function render()
    {
        return view('livewire.admin.tenant-settings')
            ->layout('layouts.app', [
                'title' => 'Paramètres vendeur',
                'subtitle' => $this->tenant->name,
            ]);
    }
}
