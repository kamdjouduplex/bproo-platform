<?php

namespace InovCom\Treasury\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use InovCom\Treasury\Services\TreasurySettings;
use Livewire\Component;

class TreasurySettingsForm extends Component
{
    public string $urgent_days = '10';
    public string $upcoming_days = '30';
    public string $alert_days = '7';

    public function mount(): void
    {
        if (!$this->can('treasury.manage_settings') && !$this->can('treasury.view')) {
            abort(403);
        }

        $settings = app(TreasurySettings::class);
        $this->urgent_days = (string) $settings->urgentDays();
        $this->upcoming_days = (string) $settings->upcomingDays();
        $this->alert_days = (string) $settings->alertDays();
    }

    public function save(): void
    {
        if (!$this->can('treasury.manage_settings')) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'urgent_days' => 'required|integer|min:1|max:90',
            'upcoming_days' => 'required|integer|min:1|max:180',
            'alert_days' => 'required|integer|min:1|max:90',
        ]);

        $settings = app(TreasurySettings::class);
        $settings->setUrgentDays((int) $data['urgent_days']);
        $settings->setUpcomingDays((int) $data['upcoming_days']);
        $settings->setAlertDays((int) $data['alert_days']);

        session()->flash('success', 'Seuils d\'alerte enregistrés.');
    }

    public function render()
    {
        return view('inovcom-treasury::livewire.settings')
            ->layout('layouts.app', [
                'title' => 'Alertes de trésorerie',
                'subtitle' => 'Prévision de trésorerie',
            ])
            ->with([
                'canManage' => $this->can('treasury.manage_settings'),
            ]);
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
