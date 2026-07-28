<?php

namespace Pressing\Http\Livewire\Settings;

use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Support\PressingSettings;

class NotificationsSettings extends Component
{
    use AuthorizesPressingActions;

    public bool $enabled = false;
    public bool $in_app = true;
    public bool $whatsapp = false;
    public bool $sms = false;
    public bool $email = false;
    public string $whatsapp_api_url = '';
    public string $whatsapp_api_key = '';
    public string $sms_api_url = '';
    public string $sms_api_key = '';
    public string $sms_sender = '';
    public string $email_from = '';

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_settings.view');
        $this->enabled = PressingSettings::notificationsEnabled();
        $this->in_app = (bool) PressingSettings::get(PressingSettings::KEY_NOTIF_IN_APP, true);
        $this->whatsapp = (bool) PressingSettings::get(PressingSettings::KEY_NOTIF_WHATSAPP, false);
        $this->sms = (bool) PressingSettings::get(PressingSettings::KEY_NOTIF_SMS, false);
        $this->email = (bool) PressingSettings::get(PressingSettings::KEY_NOTIF_EMAIL, false);
        $this->whatsapp_api_url = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_WHATSAPP_URL, '');
        $this->whatsapp_api_key = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_WHATSAPP_KEY, '');
        $this->sms_api_url = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_SMS_URL, '');
        $this->sms_api_key = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_SMS_KEY, '');
        $this->sms_sender = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_SMS_SENDER, '');
        $this->email_from = (string) PressingSettings::get(PressingSettings::KEY_NOTIF_EMAIL_FROM, '');
    }

    public function save(): void
    {
        $this->authorizePressingAction('pressing_settings.manage');

        $this->validate([
            'enabled' => ['boolean'],
            'in_app' => ['boolean'],
            'whatsapp' => ['boolean'],
            'sms' => ['boolean'],
            'email' => ['boolean'],
            'whatsapp_api_url' => ['nullable', 'string', 'max:500'],
            'whatsapp_api_key' => ['nullable', 'string', 'max:255'],
            'sms_api_url' => ['nullable', 'string', 'max:500'],
            'sms_api_key' => ['nullable', 'string', 'max:255'],
            'sms_sender' => ['nullable', 'string', 'max:50'],
            'email_from' => ['nullable', 'email', 'max:150'],
        ]);

        PressingSettings::set(PressingSettings::KEY_NOTIF_ENABLED, $this->enabled);
        PressingSettings::set(PressingSettings::KEY_NOTIF_IN_APP, $this->in_app);
        PressingSettings::set(PressingSettings::KEY_NOTIF_WHATSAPP, $this->whatsapp);
        PressingSettings::set(PressingSettings::KEY_NOTIF_SMS, $this->sms);
        PressingSettings::set(PressingSettings::KEY_NOTIF_EMAIL, $this->email);
        PressingSettings::set(PressingSettings::KEY_NOTIF_WHATSAPP_URL, trim($this->whatsapp_api_url));
        PressingSettings::set(PressingSettings::KEY_NOTIF_WHATSAPP_KEY, trim($this->whatsapp_api_key));
        PressingSettings::set(PressingSettings::KEY_NOTIF_SMS_URL, trim($this->sms_api_url));
        PressingSettings::set(PressingSettings::KEY_NOTIF_SMS_KEY, trim($this->sms_api_key));
        PressingSettings::set(PressingSettings::KEY_NOTIF_SMS_SENDER, trim($this->sms_sender));
        PressingSettings::set(PressingSettings::KEY_NOTIF_EMAIL_FROM, trim($this->email_from));

        session()->flash('success', 'Configuration des notifications enregistrée.');
    }

    public function render()
    {
        return view('pressing::livewire.settings.notifications', [
            'canManage' => $this->can('pressing_settings.manage'),
        ])->layout('layouts.app', [
            'title' => 'Notifications',
            'subtitle' => 'Paramétrage',
        ]);
    }
}
