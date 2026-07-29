<?php

namespace InovCom\Payroll\Http\Livewire;

use InovCom\Payroll\Concerns\AuthorizesPayrollActions;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Models\PayrollRun;
use InovCom\Payroll\Services\PayrollService;
use Livewire\Component;
use Livewire\WithPagination;

class PayrollRunsIndex extends Component
{
    use AuthorizesPayrollActions;
    use WithPagination;

    public string $statusFilter = 'all';
    public int $perPage = 20;

    public function mount(): void
    {
        $this->authorizePayrollAction('payroll.view');
    }

    public function cancelRun(int $runId): void
    {
        $this->authorizePayrollAction('payroll.process');

        try {
            app(PayrollService::class)->cancel(PayrollRun::findOrFail($runId));
            session()->flash('success', 'Fiche de paie annulée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $runs = PayrollRun::query()
            ->with(['processedBy'])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('period_start')
            ->paginate($this->perPage);

        $activeEmployees = Employee::where('is_active', true)->count();

        return view('inovcom-payroll::livewire.payroll-runs.index')
            ->layout('layouts.app', [
                'title' => 'Paie',
                'subtitle' => 'Périodes et bulletins',
            ])
            ->with([
                'runs' => $runs,
                'activeEmployees' => $activeEmployees,
                'tenantCode' => $this->tenantCode(),
                'canCreate' => $this->can('payroll.create'),
                'canProcess' => $this->can('payroll.process'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
