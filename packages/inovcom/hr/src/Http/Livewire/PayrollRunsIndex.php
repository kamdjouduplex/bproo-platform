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
            $run = PayrollRun::findOrFail($runId);
            if ($run->isLocked()) {
                session()->flash('error', 'Impossible d’annuler une fiche payée : les bulletins sont verrouillés.');

                return;
            }
            app(PayrollService::class)->cancel($run);
            session()->flash('success', 'Fiche de paie annulée.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $viewAll = $this->canViewAllPayroll();
        $ownEmployeeId = $viewAll ? null : $this->ownEmployeeId();

        $runsQuery = PayrollRun::query()
            ->with(['processedBy', 'lines.employee'])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(! $viewAll, function ($q) use ($ownEmployeeId) {
                if ($ownEmployeeId === null) {
                    $q->whereRaw('0 = 1');

                    return;
                }
                $q->whereHas('lines', fn ($l) => $l->where('employee_id', $ownEmployeeId));
            })
            ->orderByDesc('period_start');

        $runs = $runsQuery->paginate($this->perPage);

        $activeEmployees = $viewAll
            ? Employee::where('is_active', true)->count()
            : null;

        return view('inovcom-payroll::livewire.payroll-runs.index')
            ->layout('layouts.app', [
                'title' => $viewAll ? 'Paie' : 'Mes bulletins',
                'subtitle' => $viewAll ? 'Périodes et bulletins' : 'Vos fiches de paie',
            ])
            ->with([
                'runs' => $runs,
                'activeEmployees' => $activeEmployees,
                'tenantCode' => $this->tenantCode(),
                'canCreate' => $viewAll && $this->can('payroll.create'),
                'canProcess' => $viewAll && $this->can('payroll.process'),
                'canViewAll' => $viewAll,
                'ownEmployeeId' => $ownEmployeeId,
                'canLeave' => $this->can('payroll.leave'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
