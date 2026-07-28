<?php

namespace InovCom\Branding\Http\Livewire;

use App\Services\TenantManager;
use Livewire\Component;

class BrandingIndex extends Component
{
    public string $shop_name = '';
    public string $login_welcome_message = '';

    public function mount(): void
    {
        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            abort(404, 'Tenant non trouvé.');
        }

        $this->shop_name = (string) $tenant->getSetting('shop_name', $tenant->name);
        $this->login_welcome_message = (string) $tenant->getSetting('login_welcome_message', 'Bienvenue dans votre espace de gestion');
    }

    public function save(): void
    {
        $data = $this->validate([
            'shop_name' => 'required|string|max:255',
            'login_welcome_message' => 'required|string|max:500',
        ]);

        $tenant = app(TenantManager::class)->tenant();
        if (!$tenant) {
            return;
        }

        $tenant->setSetting('shop_name', $data['shop_name']);
        $tenant->setSetting('login_welcome_message', $data['login_welcome_message']);

        $this->dispatch('branding-updated', shopName: $data['shop_name']);

        notify()->success('Personnalisation enregistrée avec succès.');
    }

    public function render()
    {
        return view('inovcom-branding::livewire.branding.index')
            ->layout('layouts.app', [
                'title' => 'Personnalisation',
                'subtitle' => 'Logo, couleurs et messages',
            ]);
    }
}
