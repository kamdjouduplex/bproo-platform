<?php

namespace InovCom\Rh\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Carbon\Carbon;
use InovCom\Rh\Models\Employee;
use Livewire\Component;

class EmployeeForm extends Component
{
    use AuthorizesWithTenant;

    public ?Employee $emp = null;
    public bool      $isEdit = false;

    // Form fields
    public string $first_name     = '';
    public string $last_name      = '';
    public string $email          = '';
    public string $phone          = '';
    public string $position       = '';
    public string $department     = '';
    public string $contract_type  = 'cdi';
    public string $hire_date      = '';
    public string $end_date       = '';
    public string $base_salary    = '';
    public string $iban           = '';
    public string $status         = 'active';
    public string $notes          = '';

    protected function rules(): array
    {
        return [
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:30',
            'position'      => 'nullable|string|max:150',
            'department'    => 'nullable|string|max:100',
            'contract_type' => 'required|in:cdi,cdd,stage,freelance,alternance',
            'hire_date'     => 'required|date',
            'end_date'      => 'nullable|date|after_or_equal:hire_date',
            'base_salary'   => 'nullable|numeric|min:0',
            'iban'          => 'nullable|string|max:50',
            'status'        => 'required|in:active,on_leave,terminated',
            'notes'         => 'nullable|string',
        ];
    }

    public function mount(?Employee $employee = null): void
    {
        if ($employee && $employee->exists) {
            $this->tenantAuthorize('rh.edit');
            $this->isEdit        = true;
            $this->emp           = $employee;
            $this->first_name    = $employee->first_name;
            $this->last_name     = $employee->last_name;
            $this->email         = $employee->email ?? '';
            $this->phone         = $employee->phone ?? '';
            $this->position      = $employee->position ?? '';
            $this->department    = $employee->department ?? '';
            $this->contract_type = $employee->contract_type;
            $this->hire_date     = $employee->hire_date->format('Y-m-d');
            $this->end_date      = $employee->end_date ? $employee->end_date->format('Y-m-d') : '';
            $this->base_salary   = (string) $employee->base_salary;
            $this->iban          = $employee->iban ?? '';
            $this->status        = $employee->status;
            $this->notes         = $employee->notes ?? '';
        } else {
            $this->tenantAuthorize('rh.create');
            $this->hire_date = now()->toDateString();
        }
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $data = [
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'email'         => $this->email ?: null,
            'phone'         => $this->phone ?: null,
            'position'      => $this->position ?: null,
            'department'    => $this->department ?: null,
            'contract_type' => $this->contract_type,
            'hire_date'     => $this->hire_date,
            'end_date'      => $this->end_date ?: null,
            'base_salary'   => $this->base_salary ?: 0,
            'iban'          => $this->iban ?: null,
            'status'        => $this->status,
            'notes'         => $this->notes ?: null,
        ];

        $tenantCode = request()->query('tenant') ?? session('tenant_code');

        if ($this->isEdit) {
            $this->tenantAuthorize('rh.edit');
            $this->emp->update($data);
            notify()->success(__('Employé mis à jour.'));
            $this->redirect(route('tenant.rh.show', ['employee' => $this->emp->id, 'tenant' => $tenantCode]));
        } else {
            $this->tenantAuthorize('rh.create');
            $data['code'] = Employee::generateCode();
            $employee = Employee::on('tenant')->create($data);
            notify()->success(__('Employé créé.'));
            $this->redirect(route('tenant.rh.show', ['employee' => $employee->id, 'tenant' => $tenantCode]));
        }
    }

    public function cancel(): void
    {
        $tenantCode = request()->query('tenant') ?? session('tenant_code');

        if ($this->isEdit) {
            $this->redirect(route('tenant.rh.show', ['employee' => $this->emp->id, 'tenant' => $tenantCode]));
        } else {
            $this->redirect(route('tenant.rh.index', ['tenant' => $tenantCode]));
        }
    }

    public function render()
    {
        return view('inovcom-rh::livewire.employees.form')
            ->layout('layouts.app', [
                'title'    => $this->isEdit ? __('Modifier l\'employé') : __('Nouvel employé'),
                'subtitle' => $this->isEdit ? ($this->emp->code ?? '') : __('Créer une fiche employé'),
            ]);
    }
}
