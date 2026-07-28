<?php

namespace InovCom\Payroll\Http\Livewire;

use InovCom\Payroll\Concerns\AuthorizesPayrollActions;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Services\EmployeeService;
use InovCom\Payroll\Support\EmployeeRules;
use InovCom\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmployeesForm extends Component
{
    use AuthorizesPayrollActions;

    public ?int $employeeId = null;
    public string $employee_number = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';
    public string $position = '';
    public string $department = '';
    public string $contract_type = '';
    public string $cnps_number = '';
    public string $bank_name = '';
    public string $bank_account = '';
    public string $gender = '';
    public ?string $birth_date = null;
    public string $address = '';
    public string $annual_leave_days = '18';
    public string $notes = '';
    public string $base_salary = '0';
    public ?string $hire_date = null;
    public bool $is_active = true;
    public string $user_id = '';
    public string $salary_change_reason = '';

    public function mount(?Employee $employee = null): void
    {
        $this->authorizePayrollAction('payroll.employees');

        if ($employee && $employee->exists) {
            $this->employeeId = $employee->id;
            $this->employee_number = $employee->employee_number;
            $this->first_name = $employee->first_name;
            $this->last_name = $employee->last_name;
            $this->email = $employee->email ?? '';
            $this->phone = $employee->phone ?? '';
            $this->position = $employee->position ?? '';
            $this->department = $employee->department ?? '';
            $this->contract_type = $employee->contract_type ?? '';
            $this->cnps_number = $employee->cnps_number ?? '';
            $this->bank_name = $employee->bank_name ?? '';
            $this->bank_account = $employee->bank_account ?? '';
            $this->gender = $employee->gender ?? '';
            $this->birth_date = $employee->birth_date?->format('Y-m-d');
            $this->address = $employee->address ?? '';
            $this->annual_leave_days = (string) ($employee->annual_leave_days ?? 18);
            $this->notes = $employee->notes ?? '';
            $this->base_salary = (string) $employee->base_salary;
            $this->hire_date = $employee->hire_date?->format('Y-m-d');
            $this->is_active = $employee->is_active;
            $this->user_id = $employee->user_id ? (string) $employee->user_id : '';
        } else {
            $this->employee_number = app(EmployeeService::class)->nextEmployeeNumber();
        }
    }

    public function save(): void
    {
        $this->authorizePayrollAction('payroll.employees');

        $data = $this->validate(EmployeeRules::rules($this->employeeId));

        $payload = collect($data)->except(['salary_change_reason'])->all();
        $payload['user_id'] = $payload['user_id'] !== '' && $payload['user_id'] !== null
            ? (int) $payload['user_id']
            : null;
        $payload['contract_type'] = $payload['contract_type'] ?: null;
        $payload['gender'] = $payload['gender'] ?: null;
        $payload['annual_leave_days'] = (int) ($payload['annual_leave_days'] ?? 18);

        $service = app(EmployeeService::class);
        $userId = Auth::guard('tenant')->id();

        if ($this->employeeId) {
            $employee = Employee::findOrFail($this->employeeId);
            $service->update($employee, $payload, $userId, $this->salary_change_reason ?: null);
            session()->flash('success', 'Employé mis à jour.');
        } else {
            $service->create($payload, $userId);
            session()->flash('success', 'Employé créé.');
        }

        $tenantCode = $this->tenantCode();
        $this->redirect(route('tenant.payroll.employees.index', ['tenant' => $tenantCode]), navigate: true);
    }

    public function render()
    {
        return view('inovcom-payroll::livewire.employees.form')
            ->layout('layouts.app', [
                'title' => $this->employeeId ? 'Modifier l\'employé' : 'Nouvel employé',
                'subtitle' => 'Ressources humaines',
            ])
            ->with([
                'users' => User::orderBy('name')->get(['id', 'name', 'email']),
                'employeeId' => $this->employeeId,
                'tenantCode' => $this->tenantCode(),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
