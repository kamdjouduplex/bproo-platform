<?php

namespace InovCom\Rh\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Rh\Models\Employee;
use Livewire\Component;

class EmployeesIndex extends Component
{
    use AuthorizesWithTenant;

    public string $search        = '';
    public string $statusFilter  = '';
    public string $deptFilter    = '';

    public function mount(): void
    {
        $this->tenantAuthorize('rh.view');
    }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('rh.delete');
        Employee::on('tenant')->findOrFail($id)->delete();
        notify()->success(__('Employé supprimé.'));
    }

    public function render()
    {
        $query = Employee::on('tenant')->ordered();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('last_name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('email', 'ilike', '%' . $this->search . '%')
                  ->orWhere('position', 'ilike', '%' . $this->search . '%')
                  ->orWhere('code', 'ilike', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->deptFilter) {
            $query->where('department', 'ilike', '%' . $this->deptFilter . '%');
        }

        $employees   = $query->get();
        $departments = Employee::on('tenant')
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        $stats = [
            'total'      => Employee::on('tenant')->count(),
            'active'     => Employee::on('tenant')->where('status', 'active')->count(),
            'on_leave'   => Employee::on('tenant')->where('status', 'on_leave')->count(),
            'terminated' => Employee::on('tenant')->where('status', 'terminated')->count(),
        ];

        return view('inovcom-rh::livewire.employees.index', [
            'employees'   => $employees,
            'departments' => $departments,
            'stats'       => $stats,
            'canCreate'   => $this->tenantCan('rh.create'),
            'canEdit'     => $this->tenantCan('rh.edit'),
            'canDelete'   => $this->tenantCan('rh.delete'),
        ])->layout('layouts.app', [
            'title'    => __('Ressources Humaines'),
            'subtitle' => __('Gestion des employés'),
        ]);
    }
}
