<?php

namespace InovCom\Attendance\Http\Livewire;

use App\Services\TenantManager;
use Illuminate\Support\Facades\Auth;
use InovCom\Attendance\Services\AttendanceSettingsService;
use Livewire\Component;

class AttendanceSettings extends Component
{
    public string $wifi_name = '';

    public string $allowed_cidrs = '';

    public string $kiosk_code = '';

    public bool $kiosk_enabled = false;

    public string $detectedIp = '';

    public function mount(): void
    {
        if (! $this->canManage()) {
            abort(403);
        }

        $settings = app(AttendanceSettingsService::class);
        $tenant = $settings->tenant();
        if (! $tenant) {
            abort(404);
        }

        $this->wifi_name = $settings->wifiName($tenant);
        $this->allowed_cidrs = implode("\n", $settings->allowedCidrs($tenant));
        $this->kiosk_code = $settings->kioskCode($tenant);
        $this->kiosk_enabled = $settings->kioskEnabled($tenant);
        $this->detectedIp = (string) request()->ip();
    }

    public function save(): void
    {
        if (! $this->canManage()) {
            abort(403);
        }

        $data = $this->validate([
            'wifi_name' => 'nullable|string|max:120',
            'allowed_cidrs' => 'nullable|string|max:2000',
            'kiosk_enabled' => 'boolean',
        ]);

        $tenant = app(TenantManager::class)->tenant();
        if (! $tenant) {
            return;
        }

        app(AttendanceSettingsService::class)->save($tenant, [
            'wifi_name' => $data['wifi_name'] ?? '',
            'allowed_cidrs' => $data['allowed_cidrs'] ?? '',
            'kiosk_enabled' => (bool) ($data['kiosk_enabled'] ?? false),
        ]);

        session()->flash('success', 'Paramètres de présence enregistrés.');
    }

    public function useMyIp(): void
    {
        $ip = (string) request()->ip();
        if ($ip === '') {
            return;
        }
        $lines = array_filter(array_map('trim', preg_split('/\R+/', $this->allowed_cidrs) ?: []));
        if (! in_array($ip, $lines, true) && ! in_array($ip.'/32', $lines, true)) {
            $lines[] = $ip;
        }
        $this->allowed_cidrs = implode("\n", $lines);
    }

    protected function canManage(): bool
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission('attendance.settings');
    }

    public function render()
    {
        $tenant = app(TenantManager::class)->tenant();
        $tenantCode = $tenant?->code
            ?? request()->query('tenant')
            ?? session('tenant_code');

        $kioskUrl = $tenantCode && \Illuminate\Support\Facades\Route::has('tenant.attendance.kiosk')
            ? route('tenant.attendance.kiosk', ['tenant' => $tenantCode])
            : null;

        return view('inovcom-attendance::livewire.settings', [
            'tenantCode' => $tenantCode,
            'kioskUrl' => $kioskUrl,
        ])->layout('layouts.app', [
            'title' => 'Présence — Paramètres',
            'subtitle' => 'Wi‑Fi entreprise, code kiosk et pointage public',
        ]);
    }
}
