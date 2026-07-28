<?php

namespace InovCom\Payroll\Http\Livewire;

use InovCom\Payroll\Concerns\AuthorizesPayrollActions;
use InovCom\Payroll\Models\Employee;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeesIndex extends Component
{
    use AuthorizesPayrollActions;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'active';
    public int $perPage = 20;

    public function mount(): void
    {
        $this->authorizePayrollAction('payroll.view');
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function deactivate(int $id): void
    {
        $this->authorizePayrollAction('payroll.employees');
        $employee = Employee::findOrFail($id);
        $employee->update(['is_active' => false, 'termination_date' => now()]);
        session()->flash('success', 'Employé désactivé.');
        $this->resetPage();
    }

    public function render()
    {
        $employees = Employee::query()
            ->with('user')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('employee_number', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($this->perPage);

        return view('inovcom-payroll::livewire.employees.index')
            ->layout('layouts.app', [
                'title' => 'Employés',
                'subtitle' => 'Gestion RH et paie',
            ])
            ->with([
                'employees' => $employees,
                'tenantCode' => $this->tenantCode(),
                'canManage' => $this->can('payroll.employees'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
