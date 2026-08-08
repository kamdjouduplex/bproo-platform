<?php

namespace InovCom\Users\Http\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InovCom\Users\Models\Role;
use InovCom\Users\Models\User;
use Livewire\Component;

class UserForm extends Component
{
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $punch_pin = '';

    public array $role_ids = [];

    public bool $is_active = true;

    public ?int $assigned_store_id = null;

    public ?int $assigned_agence_id = null;

    // HR / employee fields (when payroll module enabled)
    public string $employee_number = '';

    public string $position = '';

    public string $department = '';

    public string $contract_type = '';

    public string $hire_date = '';

    public string $base_salary = '0';

    public string $salary_change_reason = '';

    public string $cnps_number = '';

    public string $bank_name = '';

    public string $bank_account = '';

    public string $gender = '';

    public string $birth_date = '';

    public string $address = '';

    public string $annual_leave_days = '0';

    public string $hr_notes = '';

    public function mount(?User $user = null): void
    {
        if (! $user) {
            $this->resetFormDefaults();

            return;
        }

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) ($user->phone ?? '');
        $this->password = '';
        $this->password_confirmation = '';
        $this->punch_pin = '';
        $this->role_ids = $user->roles->pluck('id')->all();
        $this->is_active = $user->is_active;
        $this->assigned_store_id = $user->assigned_store_id;
        $this->assigned_agence_id = Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id')
            ? $user->assigned_agence_id
            : null;

        $this->loadEmployeeFields($user);
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique(User::class, 'email')->ignore($this->userId)],
            'role_ids' => 'array',
            'role_ids.*' => Rule::exists(Role::class, 'id'),
            'is_active' => 'boolean',
            'assigned_store_id' => 'nullable|integer',
            'assigned_agence_id' => 'nullable|integer',
        ];

        if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
            $rules['phone'] = [
                'nullable',
                'string',
                'max:32',
                Rule::unique(User::class, 'phone')->ignore($this->userId),
            ];
        }

        if ($this->payrollEnabled()) {
            $rules['punch_pin'] = 'nullable|digits_between:4,6';
            $rules['position'] = 'nullable|string|max:128';
            $rules['department'] = 'nullable|string|max:128';
            $rules['contract_type'] = 'nullable|string|in:cdi,cdd,stage,freelance,other';
            $rules['hire_date'] = 'nullable|date';
            $rules['base_salary'] = 'nullable|numeric|min:0';
            $rules['salary_change_reason'] = 'nullable|string|max:255';
            $rules['cnps_number'] = 'nullable|string|max:64';
            $rules['bank_name'] = 'nullable|string|max:128';
            $rules['bank_account'] = 'nullable|string|max:64';
            $rules['gender'] = 'nullable|string|in:M,F,other';
            $rules['birth_date'] = 'nullable|date';
            $rules['address'] = 'nullable|string|max:500';
            $rules['annual_leave_days'] = 'nullable|integer|min:0|max:365';
            $rules['hr_notes'] = 'nullable|string|max:2000';
        }

        if (! $this->userId) {
            $rules['password'] = 'required|string|min:8|confirmed';
        } elseif (! empty($this->password)) {
            $rules['password'] = 'string|min:8|confirmed';
        }

        $data = $this->validate($rules);
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $isPressing = $tenant && (($tenant->type ?? null) === 'pressing');
        $isMultiStore = ! $isPressing
            && Schema::connection('tenant')->hasTable('stores')
            && ($tenant?->multi_store_enabled ?? true);

        if ($isMultiStore) {
            $stores = DB::connection('tenant')->table('stores')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! empty($data['assigned_store_id']) && ! in_array((int) $data['assigned_store_id'], $stores, true)) {
                $this->addError('assigned_store_id', 'Boutique invalide.');

                return;
            }
            if (empty($data['assigned_store_id'])) {
                $this->addError('assigned_store_id', 'La boutique est obligatoire.');

                return;
            }
        } else {
            $data['assigned_store_id'] = null;
        }

        $hasAgences = Schema::connection('tenant')->hasTable('agences');
        if ($hasAgences && Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id')) {
            if (! empty($data['assigned_agence_id'])) {
                $ok = DB::connection('tenant')->table('agences')->where('id', (int) $data['assigned_agence_id'])->exists();
                if (! $ok) {
                    $this->addError('assigned_agence_id', 'Agence invalide.');

                    return;
                }
            } elseif ($isPressing) {
                $this->addError('assigned_agence_id', 'L’agence de travail est obligatoire.');

                return;
            }
        }

        $user = $this->userId
            ? User::on('tenant')->findOrFail($this->userId)
            : User::on('tenant')->make();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_active = $data['is_active'];
        $user->assigned_store_id = $data['assigned_store_id'] ?? null;
        if (Schema::connection('tenant')->hasColumn('users', 'phone')) {
            $digits = preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')) ?? '';
            $user->phone = $digits !== '' ? $digits : null;
        }
        if (Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id')) {
            $user->assigned_agence_id = $data['assigned_agence_id'] ?? null;
        }
        if (! $this->userId) {
            $user->remember_token = Str::random(10);
        }
        if (! empty($data['password'] ?? '')) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->roles()->sync($data['role_ids'] ?? []);

        $message = $this->userId ? 'Utilisateur mis à jour.' : 'Utilisateur créé.';

        if ($this->payrollEnabled()) {
            try {
                $sync = app(\InovCom\Payroll\Services\UserEmployeeSyncService::class);
                $result = $sync->ensureForUser(
                    $user,
                    $user->phone,
                    ! empty($data['punch_pin'] ?? '') ? (string) $data['punch_pin'] : null,
                    [
                        'position' => $data['position'] ?? null,
                        'department' => $data['department'] ?? null,
                        'contract_type' => ($data['contract_type'] ?? '') ?: null,
                        'hire_date' => ($data['hire_date'] ?? '') ?: null,
                        'base_salary' => $data['base_salary'] ?? 0,
                        'salary_change_reason' => $data['salary_change_reason'] ?? null,
                        'cnps_number' => $data['cnps_number'] ?? null,
                        'bank_name' => $data['bank_name'] ?? null,
                        'bank_account' => $data['bank_account'] ?? null,
                        'gender' => ($data['gender'] ?? '') ?: null,
                        'birth_date' => ($data['birth_date'] ?? '') ?: null,
                        'address' => $data['address'] ?? null,
                        'annual_leave_days' => $data['annual_leave_days'] ?? 0,
                        'notes' => $data['hr_notes'] ?? null,
                    ]
                );
                $this->employee_number = (string) $result['employee']->employee_number;
                if ($result['created']) {
                    $message .= ' Matricule '.$result['employee']->employee_number.'.';
                }
                if ($result['plain_pin']) {
                    $message .= ' Code pointage : '.$result['plain_pin'].'.';
                }
            } catch (\Throwable $e) {
                $message .= ' (Sync employé : '.$e->getMessage().')';
            }
        }

        notify()->success($message);

        return $this->redirect(route('tenant.users.index', ['tenant' => $this->tenantCode()]), navigate: false);
    }

    private function loadEmployeeFields(User $user): void
    {
        if (! $this->payrollEnabled()) {
            return;
        }

        $employee = \InovCom\Payroll\Models\Employee::query()->where('user_id', $user->id)->first();
        if (! $employee) {
            return;
        }

        $this->employee_number = (string) $employee->employee_number;
        $this->position = (string) ($employee->position ?? '');
        $this->department = (string) ($employee->department ?? '');
        $this->contract_type = (string) ($employee->contract_type ?? '');
        $this->hire_date = $employee->hire_date?->format('Y-m-d') ?? '';
        $this->base_salary = (string) ($employee->base_salary ?? '0');
        $this->cnps_number = (string) ($employee->cnps_number ?? '');
        $this->bank_name = (string) ($employee->bank_name ?? '');
        $this->bank_account = (string) ($employee->bank_account ?? '');
        $this->gender = (string) ($employee->gender ?? '');
        $this->birth_date = $employee->birth_date?->format('Y-m-d') ?? '';
        $this->address = (string) ($employee->address ?? '');
        $this->annual_leave_days = (string) ($employee->annual_leave_days ?? '0');
        $this->hr_notes = (string) ($employee->notes ?? '');
        if ($this->phone === '' && $employee->phone) {
            $this->phone = (string) $employee->phone;
        }
    }

    private function resetFormDefaults(): void
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->punch_pin = '';
        $this->role_ids = [];
        $this->is_active = true;
        $this->assigned_store_id = null;
        $this->assigned_agence_id = null;
        $this->employee_number = '';
        $this->position = '';
        $this->department = '';
        $this->contract_type = '';
        $this->hire_date = now()->format('Y-m-d');
        $this->base_salary = '0';
        $this->salary_change_reason = '';
        $this->cnps_number = '';
        $this->bank_name = '';
        $this->bank_account = '';
        $this->gender = '';
        $this->birth_date = '';
        $this->address = '';
        $this->annual_leave_days = '0';
        $this->hr_notes = '';
    }

    private function payrollEnabled(): bool
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        if (! $tenant || ! class_exists(\App\Services\ModuleRegistry::class)) {
            return false;
        }

        return app(\App\Services\ModuleRegistry::class)->isEnabled('payroll', $tenant)
            && class_exists(\InovCom\Payroll\Services\UserEmployeeSyncService::class)
            && Schema::connection('tenant')->hasTable('employees');
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    public function render()
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $isPressing = $tenant && (($tenant->type ?? null) === 'pressing');
        $showStores = ! $isPressing
            && Schema::connection('tenant')->hasTable('stores')
            && ($tenant?->multi_store_enabled ?? false);

        $stores = $showStores
            ? DB::connection('tenant')->table('stores')->orderBy('name')->get(['id', 'name'])
            : collect();

        $agences = Schema::connection('tenant')->hasTable('agences')
            ? DB::connection('tenant')->table('agences')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('inovcom-users::livewire.users.form')
            ->layout('layouts.app', [
                'title' => $this->userId ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur',
                'subtitle' => $this->payrollEnabled() ? 'Compte + fiche employé (paie / présence)' : '',
            ])
            ->with([
                'roles' => Role::on('tenant')->orderBy('name')->get(),
                'stores' => $stores,
                'agences' => $agences,
                'canAssignAgence' => Schema::connection('tenant')->hasColumn('users', 'assigned_agence_id'),
                'isPressingTenant' => $isPressing,
                'payrollEnabled' => $this->payrollEnabled(),
                'hasPhoneColumn' => Schema::connection('tenant')->hasColumn('users', 'phone'),
            ]);
    }
}
