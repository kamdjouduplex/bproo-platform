<?php

namespace InovCom\Users\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use InovCom\Users\Models\User;
use Livewire\Component;

class AccountProfile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $preferred_locale = 'fr';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $punch_pin = '';

    public string $punch_pin_confirmation = '';

    public ?string $matricule = null;

    public ?string $position = null;

    public bool $hasEmployee = false;

    public bool $hasAttendance = false;

    public bool $canPunchIn = false;

    public bool $canPunchOut = false;

    public bool $isPresent = false;

    public ?string $arrivalTime = null;

    public ?string $departureTime = null;

    public function mount(): void
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            abort(403);
        }

        $this->hydrateFromUser($user);
        $this->refreshPunchStatus();
    }

    public function saveProfile(): void
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique(User::class, 'email')->ignore($user->id)],
        ];
        if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
            $rules['phone'] = [
                'nullable',
                'string',
                'max:32',
                Rule::unique(User::class, 'phone')->ignore($user->id),
            ];
        }
        if (Schema::connection('tenant')->hasColumn('users', 'preferred_locale')) {
            $localeKeys = config('inovcom.supported_locales', ['fr', 'en']);
            $rules['preferred_locale'] = ['required', 'string', Rule::in(is_array($localeKeys) ? $localeKeys : ['fr'])];
        }

        $data = $this->validate($rules);
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
            $user->phone = $this->digits($data['phone'] ?? '') ?: null;
        }
        if (Schema::connection('tenant')->hasColumn('users', 'preferred_locale')) {
            $user->preferred_locale = $data['preferred_locale'] ?? 'fr';
            session(['locale' => $user->preferred_locale]);
            app()->setLocale($user->preferred_locale);
        }
        $user->save();

        if ($this->payrollSyncAvailable()) {
            app(\InovCom\Payroll\Services\UserEmployeeSyncService::class)
                ->ensureForUser($user, $user->phone, null);
        }

        $this->hydrateFromUser($user->fresh());
        session()->flash('success', 'Profil mis à jour.');
    }

    public function changePassword(): void
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();
        $this->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Mot de passe actuel incorrect.');

            return;
        }

        $user->password = Hash::make($this->password);
        $user->save();
        $this->reset(['current_password', 'password', 'password_confirmation']);
        session()->flash('success', 'Mot de passe modifié.');
    }

    public function changePunchPin(): void
    {
        if (! $this->payrollSyncAvailable()) {
            session()->flash('error', 'Module paie indisponible.');

            return;
        }

        $this->validate([
            'punch_pin' => 'required|digits_between:4,6|confirmed',
        ], [], [
            'punch_pin' => 'code de pointage',
        ]);

        /** @var User $user */
        $user = Auth::guard('tenant')->user();
        $sync = app(\InovCom\Payroll\Services\UserEmployeeSyncService::class);
        $result = $sync->ensureForUser($user, $user->phone, null);
        $sync->setPunchPin($result['employee'], $this->punch_pin);
        $this->reset(['punch_pin', 'punch_pin_confirmation']);
        session()->flash('success', 'Code de pointage mis à jour. Utilisez-le avec votre téléphone sur le kiosk.');
    }

    public function punchIn(): void
    {
        $this->doPunch('in');
    }

    public function punchOut(): void
    {
        $this->doPunch('out');
    }

    protected function doPunch(string $type): void
    {
        $user = Auth::guard('tenant')->user();
        if (! $user || ! $this->hasAttendance || ! class_exists(\InovCom\Attendance\Services\AttendanceService::class)) {
            session()->flash('error', 'Module présence indisponible.');

            return;
        }

        if (method_exists($user, 'hasPermission')
            && ! $user->isAdmin()
            && ! $user->hasPermission('attendance.punch')) {
            session()->flash('error', 'Permission refusée pour le pointage.');

            return;
        }

        $service = app(\InovCom\Attendance\Services\AttendanceService::class);
        $result = $type === 'out' ? $service->punchOut($user) : $service->punchIn($user);
        session()->flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->refreshPunchStatus();
    }

    protected function refreshPunchStatus(): void
    {
        $user = Auth::guard('tenant')->user();
        if (! $user || ! $this->hasAttendance || ! class_exists(\InovCom\Attendance\Services\AttendanceService::class)) {
            return;
        }

        $status = app(\InovCom\Attendance\Services\AttendanceService::class)->todayStatus($user);
        $this->canPunchIn = (bool) $status['can_punch_in'];
        $this->canPunchOut = (bool) $status['can_punch_out'];
        $this->isPresent = (bool) $status['is_present'];
        $this->arrivalTime = $status['arrival_time'];
        $this->departureTime = $status['departure_time'];
    }

    protected function hydrateFromUser(User $user): void
    {
        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) ($user->phone ?? '');
        $this->preferred_locale = (string) ($user->preferred_locale ?? app()->getLocale() ?? 'fr');
        $this->hasEmployee = false;
        $this->matricule = null;
        $this->position = null;

        $tenant = app()->bound('tenant') ? app('tenant') : app(\App\Services\TenantManager::class)->tenant();
        $this->hasAttendance = (bool) ($tenant
            && class_exists(\App\Services\ModuleRegistry::class)
            && app(\App\Services\ModuleRegistry::class)->isEnabled('attendance', $tenant));

        if ($this->payrollSyncAvailable()) {
            $employee = \InovCom\Payroll\Models\Employee::query()->where('user_id', $user->id)->first();
            if ($employee) {
                $this->hasEmployee = true;
                $this->matricule = $employee->employee_number;
                $this->position = $employee->position;
                if ($this->phone === '' && $employee->phone) {
                    $this->phone = (string) $employee->phone;
                }
            }
        }
    }

    protected function payrollSyncAvailable(): bool
    {
        $tenant = app()->bound('tenant') ? app('tenant') : app(\App\Services\TenantManager::class)->tenant();

        return class_exists(\InovCom\Payroll\Services\UserEmployeeSyncService::class)
            && Schema::connection('tenant')->hasTable('employees')
            && $tenant
            && class_exists(\App\Services\ModuleRegistry::class)
            && app(\App\Services\ModuleRegistry::class)->isEnabled('payroll', $tenant);
    }

    protected function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public function render()
    {
        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;

        $canPunch = false;
        $user = Auth::guard('tenant')->user();
        if ($this->hasAttendance && $user) {
            $canPunch = method_exists($user, 'isAdmin') && $user->isAdmin()
                || (method_exists($user, 'hasPermission') && $user->hasPermission('attendance.punch'));
        }

        return view('inovcom-users::livewire.account.profile', [
            'tenantCode' => $tenantCode,
            'canPunch' => $canPunch,
        ])->layout('layouts.app', [
            'title' => 'Mon compte',
            'subtitle' => 'Profil, présence et sécurité',
        ]);
    }
}
