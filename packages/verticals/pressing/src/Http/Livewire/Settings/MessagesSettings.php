<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Support\PressingSettings;

class MessagesSettings extends Component
{
    use AuthorizesPressingActions;

    public string $order_created = '';
    public string $order_ready = '';
    public string $order_delivered = '';
    public string $payment_received = '';
    public string $payment_reminder = '';
    public string $order_overdue = '';

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_settings.view');
        $this->order_created = PressingSettings::message(PressingSettings::KEY_MSG_ORDER_CREATED);
        $this->order_ready = PressingSettings::message(PressingSettings::KEY_MSG_ORDER_READY);
        $this->order_delivered = PressingSettings::message(PressingSettings::KEY_MSG_ORDER_DELIVERED);
        $this->payment_received = PressingSettings::message(PressingSettings::KEY_MSG_PAYMENT_RECEIVED);
        $this->payment_reminder = PressingSettings::message(PressingSettings::KEY_MSG_PAYMENT_REMINDER);
        $this->order_overdue = PressingSettings::message(PressingSettings::KEY_MSG_ORDER_OVERDUE);
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');

        $data = $this->validate([
            'order_created' => ['required', 'string', 'max:2000'],
            'order_ready' => ['required', 'string', 'max:2000'],
            'order_delivered' => ['required', 'string', 'max:2000'],
            'payment_received' => ['required', 'string', 'max:2000'],
            'payment_reminder' => ['required', 'string', 'max:2000'],
            'order_overdue' => ['required', 'string', 'max:2000'],
        ]);

        PressingSettings::set(PressingSettings::KEY_MSG_ORDER_CREATED, $data['order_created']);
        PressingSettings::set(PressingSettings::KEY_MSG_ORDER_READY, $data['order_ready']);
        PressingSettings::set(PressingSettings::KEY_MSG_ORDER_DELIVERED, $data['order_delivered']);
        PressingSettings::set(PressingSettings::KEY_MSG_PAYMENT_RECEIVED, $data['payment_received']);
        PressingSettings::set(PressingSettings::KEY_MSG_PAYMENT_REMINDER, $data['payment_reminder']);
        PressingSettings::set(PressingSettings::KEY_MSG_ORDER_OVERDUE, $data['order_overdue']);

        session()->flash('success', 'Modèles de messages enregistrés.');
    }

    public function resetDefaults(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');
        $defaults = PressingSettings::defaultMessages();
        $this->order_created = $defaults[PressingSettings::KEY_MSG_ORDER_CREATED];
        $this->order_ready = $defaults[PressingSettings::KEY_MSG_ORDER_READY];
        $this->order_delivered = $defaults[PressingSettings::KEY_MSG_ORDER_DELIVERED];
        $this->payment_received = $defaults[PressingSettings::KEY_MSG_PAYMENT_RECEIVED];
        $this->payment_reminder = $defaults[PressingSettings::KEY_MSG_PAYMENT_REMINDER];
        $this->order_overdue = $defaults[PressingSettings::KEY_MSG_ORDER_OVERDUE];
    }

    public function render()
    {
        return view('pressing::livewire.settings.messages', [
            'canManage' => $this->can('pressing_settings.manage'),
        ])->layout('layouts.app', [
            'title' => 'Modèles de messages',
            'subtitle' => 'Paramétrage',
        ]);
    }
}
