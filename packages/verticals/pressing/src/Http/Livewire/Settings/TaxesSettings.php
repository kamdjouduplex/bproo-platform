<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Support\PressingSettings;

class TaxesSettings extends Component
{
    use AuthorizesPressingActions;

    public bool $tax_enabled = false;
    public string $tax_rate = '0';

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_settings.view');
        $this->tax_enabled = PressingSettings::taxEnabled();
        $this->tax_rate = (string) PressingSettings::taxRate();
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');

        $data = $this->validate([
            'tax_enabled' => ['boolean'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        PressingSettings::set(PressingSettings::KEY_TAX_ENABLED, (bool) $data['tax_enabled']);
        PressingSettings::set(PressingSettings::KEY_TAX_RATE, (float) $data['tax_rate']);
        session()->flash('success', 'Taxes enregistrées.');
    }

    public function render()
    {
        return view('pressing::livewire.settings.taxes')->layout('layouts.app', [
            'title' => 'Taxes',
            'subtitle' => 'Paramétrage',
        ]);
    }
}
