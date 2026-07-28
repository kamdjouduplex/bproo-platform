<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Support\PressingSettings;

class DelaysSettings extends Component
{
    use AuthorizesPressingActions;

    public string $default_delay_hours = '24';

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_settings.view');
        $this->default_delay_hours = (string) PressingSettings::defaultDelayHours();
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');

        $data = $this->validate([
            'default_delay_hours' => ['required', 'integer', 'min:0', 'max:720'],
        ]);

        PressingSettings::set(PressingSettings::KEY_DEFAULT_DELAY_HOURS, (int) $data['default_delay_hours']);
        session()->flash('success', 'Délais enregistrés.');
    }

    public function render()
    {
        return view('pressing::livewire.settings.delays')->layout('layouts.app', [
            'title' => 'Délais',
            'subtitle' => 'Paramétrage',
        ]);
    }
}
