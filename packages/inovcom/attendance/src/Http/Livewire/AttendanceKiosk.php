<?php

namespace InovCom\Attendance\Http\Livewire;

use App\Services\ModuleRegistry;
use App\Services\TenantManager;
use InovCom\Attendance\Services\AttendanceService;
use InovCom\Attendance\Services\AttendanceSettingsService;
use InovCom\Payroll\Services\UserEmployeeSyncService;
use Livewire\Component;

class AttendanceKiosk extends Component
{
    public string $phone = '';

    public string $code = '';

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public ?string $employeeName = null;

    public bool $canPunchIn = false;

    public bool $canPunchOut = false;

    public ?string $arrivalTime = null;

    public ?string $departureTime = null;

    public bool $identified = false;

    public function mount(): void
    {
        $tenant = app(TenantManager::class)->tenant();
        if (! $tenant || ! app(ModuleRegistry::class)->isEnabled('attendance', $tenant)) {
            abort(403, 'Module présence non disponible.');
        }

        $settings = app(AttendanceSettingsService::class);
        if (! $settings->kioskEnabled($tenant)) {
            abort(403, 'Le pointage public n’est pas activé. Demandez à un administrateur de l’activer.');
        }
    }

    public function identify(): void
    {
        $this->resetFlash();
        $this->identified = false;
        $this->employeeName = null;

        $this->validate([
            'phone' => 'required|string|min:6|max:30',
            'code' => 'required|string|min:4|max:12',
        ], [
            'phone.required' => 'Saisissez votre numéro de téléphone.',
            'code.required' => 'Saisissez votre code de pointage personnel.',
        ]);

        $identity = $this->resolveIdentity();
        if ($identity['error'] || ! $identity['user']) {
            $this->flash((string) ($identity['error'] ?? 'Identifiant invalide.'), 'error');

            return;
        }

        $this->identified = true;
        $this->employeeName = $identity['employee']->full_name ?? $identity['user']->name;
        $this->refreshStatusForUser($identity['user']);
    }

    public function punchIn(): void
    {
        $this->punch('in');
    }

    public function punchOut(): void
    {
        $this->punch('out');
    }

    public function resetSession(): void
    {
        $this->reset(['phone', 'code', 'identified', 'employeeName', 'arrivalTime', 'departureTime', 'canPunchIn', 'canPunchOut']);
        $this->resetFlash();
    }

    protected function punch(string $type): void
    {
        $this->resetFlash();

        if (! $this->identified) {
            $this->flash('Identifiez-vous d’abord avec votre numéro et votre code.', 'error');

            return;
        }

        $identity = $this->resolveIdentity();
        if ($identity['error'] || ! $identity['user']) {
            $this->flash((string) ($identity['error'] ?? 'Employé introuvable.'), 'error');
            $this->identified = false;

            return;
        }

        $user = $identity['user'];
        $service = app(AttendanceService::class);
        $result = $type === 'out'
            ? $service->punchOut($user, null, 'kiosk')
            : $service->punchIn($user, null, 'kiosk');

        $this->flash((string) $result['message'], $result['success'] ? 'success' : 'error');
        $this->refreshStatusForUser($user);
    }

    /**
     * @return array{user: ?\InovCom\Users\Models\User, employee: ?object, error: ?string}
     */
    protected function resolveIdentity(): array
    {
        $settings = app(AttendanceSettingsService::class);
        $employee = $settings->findEmployeeByPhone($this->phone);
        if (! $employee) {
            return [
                'user' => null,
                'employee' => null,
                'error' => 'Aucun employé actif trouvé avec ce numéro. Vérifiez le téléphone sur votre fiche / Mon compte.',
            ];
        }

        if (! class_exists(UserEmployeeSyncService::class)) {
            return [
                'user' => null,
                'employee' => $employee,
                'error' => 'Module paie requis pour le code personnel.',
            ];
        }

        $sync = app(UserEmployeeSyncService::class);
        if (! $sync->verifyPunchPin($employee, $this->code)) {
            return [
                'user' => null,
                'employee' => $employee,
                'error' => 'Code de pointage incorrect. Vous pouvez le modifier dans Mon compte une fois connecté.',
            ];
        }

        $user = null;
        if (! empty($employee->user_id)) {
            $user = \InovCom\Users\Models\User::query()
                ->where('id', $employee->user_id)
                ->where('is_active', true)
                ->first();
        }
        if (! $user && ! empty($employee->email)) {
            $user = \InovCom\Users\Models\User::query()
                ->where('email', $employee->email)
                ->where('is_active', true)
                ->first();
        }

        if (! $user) {
            return [
                'user' => null,
                'employee' => $employee,
                'error' => 'Employé trouvé ('.$employee->full_name.'), mais aucun compte utilisateur lié. '
                    .'Créez ou liez le compte dans Utilisateurs.',
            ];
        }

        return [
            'user' => $user,
            'employee' => $employee,
            'error' => null,
        ];
    }

    protected function refreshStatusForUser(object $user): void
    {
        $status = app(AttendanceService::class)->todayStatus($user);
        $this->canPunchIn = (bool) $status['can_punch_in'];
        $this->canPunchOut = (bool) $status['can_punch_out'];
        $this->arrivalTime = $status['arrival_time'];
        $this->departureTime = $status['departure_time'];
    }

    protected function flash(string $message, string $type): void
    {
        $this->flashMessage = $message;
        $this->flashType = $type;
    }

    protected function resetFlash(): void
    {
        $this->flashMessage = null;
    }

    public function render()
    {
        $settings = app(AttendanceSettingsService::class);
        $tenant = $settings->tenant();
        $network = $settings->assertOnCompanyNetwork();

        return view('inovcom-attendance::livewire.kiosk', [
            'tenantName' => $tenant?->getSetting('shop_name', $tenant?->name) ?? config('app.name'),
            'wifiName' => $settings->wifiName($tenant) ?: 'Wi‑Fi de l’entreprise',
            'networkOk' => $network['allowed'],
            'networkMessage' => $network['message'],
            'networkConfigured' => $settings->networkRestrictionConfigured($tenant),
            'clientIp' => $network['client_ip'],
            'tenantCode' => $tenant?->code,
        ])->layout('inovcom-attendance::layouts.kiosk');
    }
}
