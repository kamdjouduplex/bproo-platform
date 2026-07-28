<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use Livewire\Component;

class TenantSettings extends Component
{
    public Tenant $tenant;

    public string $currency = 'XOF';
    public string $locale = 'fr';
    public string $timezone = 'Africa/Abidjan';
    public string $tax_rate = '0';
    public string $invoice_prefix = 'INV';

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->currency = (string) $tenant->getSetting('currency', 'XOF');
        $this->locale = (string) $tenant->getSetting('locale', 'fr');
        $this->timezone = (string) $tenant->getSetting('timezone', 'Africa/Abidjan');
        $this->tax_rate = (string) $tenant->getSetting('tax_rate', '0');
        $this->invoice_prefix = (string) $tenant->getSetting('invoice_prefix', 'INV');
    }

    public function save(): void
    {
        $data = $this->validate([
            'currency' => 'required|string|max:10',
            'locale' => 'required|string|max:10',
            'timezone' => 'required|string|max:64',
            'tax_rate' => 'required|numeric|min:0',
            'invoice_prefix' => 'required|string|max:20',
        ]);

        $this->tenant->setSetting('currency', $data['currency']);
        $this->tenant->setSetting('locale', $data['locale']);
        $this->tenant->setSetting('timezone', $data['timezone']);
        $this->tenant->setSetting('tax_rate', $data['tax_rate']);
        $this->tenant->setSetting('invoice_prefix', $data['invoice_prefix']);
    }

    public function render()
    {
        return view('livewire.admin.tenant-settings')
            ->layout('layouts.app', [
                'title' => __('Paramètres entreprise'),
                'subtitle' => $this->tenant->name,
            ]);
    }
}
