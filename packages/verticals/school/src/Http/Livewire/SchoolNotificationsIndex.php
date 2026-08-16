<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolNotificationLog;
use School\Support\SchoolNotificationSettings;

class SchoolNotificationsIndex extends Component
{
    use ResolvesTenantCode;
    use WithPagination;

    public string $filterEvent = '';
    public string $filterChannel = '';
    public string $filterStatus = '';

    public bool $enabled = true;
    public bool $channelSms = true;
    public bool $channelEmail = true;
    public string $smsDriver = 'http';
    public string $smsUrl = '';
    public string $smsKey = '';
    public string $smsSender = '';
    public string $twilioSid = '';
    public string $twilioToken = '';
    public string $twilioFrom = '';
    public string $emailFrom = '';
    public string $msgEnrollment = '';
    public string $msgPayment = '';
    public string $msgResults = '';
    public string $msgReportCard = '';
    public string $msgAnnouncement = '';
    public bool $showSettings = false;

    public string $announceMessage = '';
    public string $announcePhone = '';
    public string $announceEmail = '';

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $this->enabled = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_ENABLED) === '1';
        $this->channelSms = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS) === '1';
        $this->channelEmail = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_EMAIL) === '1';
        $this->smsDriver = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_DRIVER) ?: 'http';
        $this->smsUrl = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_URL);
        $this->smsKey = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_KEY);
        $this->smsSender = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_SMS_SENDER);
        $this->twilioSid = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_TWILIO_SID);
        $this->twilioToken = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_TWILIO_TOKEN);
        $this->twilioFrom = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_TWILIO_FROM);
        $this->emailFrom = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_EMAIL_FROM);
        $this->msgEnrollment = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_MSG_ENROLLMENT);
        $this->msgPayment = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_MSG_PAYMENT);
        $this->msgResults = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_MSG_RESULTS);
        $this->msgReportCard = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_MSG_REPORT_CARD);
        $this->msgAnnouncement = SchoolNotificationSettings::get(SchoolNotificationSettings::KEY_MSG_ANNOUNCEMENT);
    }

    public function openSettings(): void
    {
        $this->loadSettings();
        $this->showSettings = true;
    }

    public function closeSettings(): void
    {
        $this->showSettings = false;
    }

    public function saveSettings(): void
    {
        $this->validate([
            'smsDriver' => ['required', 'in:http,twilio'],
        ]);

        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_ENABLED, $this->enabled ? '1' : '0');
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_SMS, $this->channelSms ? '1' : '0');
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_EMAIL, $this->channelEmail ? '1' : '0');
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_SMS_DRIVER, $this->smsDriver);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_SMS_URL, $this->smsUrl);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_SMS_KEY, $this->smsKey);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_SMS_SENDER, $this->smsSender);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_TWILIO_SID, $this->twilioSid);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_TWILIO_TOKEN, $this->twilioToken);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_TWILIO_FROM, $this->twilioFrom);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_EMAIL_FROM, $this->emailFrom);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_MSG_ENROLLMENT, $this->msgEnrollment);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_MSG_PAYMENT, $this->msgPayment);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_MSG_RESULTS, $this->msgResults);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_MSG_REPORT_CARD, $this->msgReportCard);
        SchoolNotificationSettings::set(SchoolNotificationSettings::KEY_MSG_ANNOUNCEMENT, $this->msgAnnouncement);
        notify()->success('Paramètres notifications enregistrés.');
        $this->showSettings = false;
    }

    public function sendAnnouncement(): void
    {
        $this->validate([
            'announceMessage' => ['required', 'string', 'max:1000'],
            'announcePhone' => ['nullable', 'string', 'max:80'],
            'announceEmail' => ['nullable', 'email', 'max:255'],
        ]);

        app(\School\Support\SchoolNotificationDispatcher::class)->dispatch('announcement', null, [
            'message' => $this->announceMessage,
            'phone' => $this->announcePhone ?: null,
            'email' => $this->announceEmail ?: null,
        ]);
        notify()->success('Annonce envoyée (selon canaux configurés).');
        $this->announceMessage = $this->announcePhone = $this->announceEmail = '';
    }

    public function updatedFilterEvent(): void { $this->resetPage(); }
    public function updatedFilterChannel(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function render()
    {
        $rows = SchoolNotificationLog::query()
            ->with('student')
            ->when($this->filterEvent !== '', fn ($q) => $q->where('event', $this->filterEvent))
            ->when($this->filterChannel !== '', fn ($q) => $q->where('channel', $this->filterChannel))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('id')
            ->paginate(20);

        return view('school::livewire.school.notifications.index', [
            'rows' => $rows,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Notifications',
            'subtitle' => 'SMS / Email — journaux et configuration.',
        ]);
    }
}
