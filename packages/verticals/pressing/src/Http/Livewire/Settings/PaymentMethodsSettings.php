<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Support\PressingSettings;

class PaymentMethodsSettings extends Component
{
    use AuthorizesPressingActions;

    public array $methods = [];

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_settings.view');
        $this->methods = PressingSettings::paymentMethods(false);
        if (count($this->methods) === 0) {
            $this->methods = PressingSettings::defaultPaymentMethods();
        }
    }

    public function addMethod(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');
        $this->methods[] = [
            'key' => 'custom_' . (count($this->methods) + 1),
            'label' => '',
            'is_active' => true,
        ];
    }

    public function removeMethod(int $index): void
    {
        $this->authorizePressingAction('pressing_settings.manage');
        unset($this->methods[$index]);
        $this->methods = array_values($this->methods);
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');

        $this->validate([
            'methods' => ['required', 'array', 'min:1'],
            'methods.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'methods.*.label' => ['required', 'string', 'max:100'],
            'methods.*.is_active' => ['boolean'],
        ]);

        $normalized = [];
        $keys = [];
        foreach ($this->methods as $method) {
            $key = strtolower(trim($method['key']));
            if (in_array($key, $keys, true)) {
                $this->addError('methods', 'Chaque clé de paiement doit être unique.');
                return;
            }
            $keys[] = $key;
            $normalized[] = [
                'key' => $key,
                'label' => trim($method['label']),
                'is_active' => (bool) ($method['is_active'] ?? true),
            ];
        }

        PressingSettings::set(PressingSettings::KEY_PAYMENT_METHODS, $normalized);
        $this->methods = $normalized;
        session()->flash('success', 'Types de paiement enregistrés.');
    }

    public function render()
    {
        return view('pressing::livewire.settings.payments', [
            'canManage' => $this->can('pressing_settings.manage'),
        ])->layout('layouts.app', [
            'title' => 'Types de paiement',
            'subtitle' => 'Paramétrage',
        ]);
    }
}
