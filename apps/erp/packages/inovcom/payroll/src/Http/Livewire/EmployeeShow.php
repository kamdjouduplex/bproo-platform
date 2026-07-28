<?php

namespace InovCom\Payroll\Http\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InovCom\Payroll\Concerns\AuthorizesPayrollActions;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Models\PayrollLine;
use InovCom\Payroll\Services\LeaveService;
use InovCom\Payroll\Services\PayrollAdjustmentService;
use Livewire\Component;

class EmployeeShow extends Component
{
    use AuthorizesPayrollActions;

    public Employee $employee;

    public string $adj_period_start = '';
    public string $adj_period_end = '';

    public string $unpaid_days = '1';
    public string $unpaid_reason = '';

    public string $bonus_amount = '';
    public string $bonus_reason = '';

    public string $deduction_amount = '';
    public string $deduction_reason = '';

    public function mount(Employee $employee): void
    {
        $this->authorizePayrollAction('payroll.view');
        $this->employee = $employee->load([
            'user',
            'salaryHistory.changedBy',
            'leaveRequests.leaveType',
            'leaveBalances.leaveType',
            'payrollAdjustments.recordedBy',
        ]);

        $start = now()->startOfMonth();
        $this->adj_period_start = $start->format('Y-m-d');
        $this->adj_period_end = $start->copy()->endOfMonth()->format('Y-m-d');
    }

    public function addUnpaidDays(): void
    {
        $this->authorizePayrollAction('payroll.adjustments');
        $this->validate([
            'adj_period_start' => 'required|date',
            'adj_period_end' => 'required|date|after_or_equal:adj_period_start',
            'unpaid_days' => 'required|numeric|min:0.5|max:31',
            'unpaid_reason' => 'required|string|max:255',
        ]);

        $result = app(PayrollAdjustmentService::class)->recordUnpaidDays(
            $this->employee,
            Carbon::parse($this->adj_period_start),
            Carbon::parse($this->adj_period_end),
            (float) $this->unpaid_days,
            $this->unpaid_reason,
            Auth::guard('tenant')->id()
        );

        session()->flash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success']) {
            $this->reset(['unpaid_days', 'unpaid_reason']);
            $this->unpaid_days = '1';
            $this->refreshEmployee();
        }
    }

    public function addBonus(): void
    {
        $this->authorizePayrollAction('payroll.adjustments');
        $this->validate([
            'adj_period_start' => 'required|date',
            'adj_period_end' => 'required|date|after_or_equal:adj_period_start',
            'bonus_amount' => 'required|numeric|min:1',
            'bonus_reason' => 'required|string|max:255',
        ]);

        $result = app(PayrollAdjustmentService::class)->recordBonus(
            $this->employee,
            Carbon::parse($this->adj_period_start),
            Carbon::parse($this->adj_period_end),
            (float) $this->bonus_amount,
            $this->bonus_reason,
            Auth::guard('tenant')->id()
        );

        session()->flash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success']) {
            $this->reset(['bonus_amount', 'bonus_reason']);
            $this->refreshEmployee();
        }
    }

    public function addDeduction(): void
    {
        $this->authorizePayrollAction('payroll.adjustments');
        $this->validate([
            'adj_period_start' => 'required|date',
            'adj_period_end' => 'required|date|after_or_equal:adj_period_start',
            'deduction_amount' => 'required|numeric|min:1',
            'deduction_reason' => 'required|string|max:255',
        ]);

        $result = app(PayrollAdjustmentService::class)->recordDeduction(
            $this->employee,
            Carbon::parse($this->adj_period_start),
            Carbon::parse($this->adj_period_end),
            (float) $this->deduction_amount,
            $this->deduction_reason,
            Auth::guard('tenant')->id()
        );

        session()->flash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success']) {
            $this->reset(['deduction_amount', 'deduction_reason']);
            $this->refreshEmployee();
        }
    }

    public function deleteAdjustment(int $id): void
    {
        $this->authorizePayrollAction('payroll.adjustments');
        $result = app(PayrollAdjustmentService::class)->delete($id);
        session()->flash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success']) {
            $this->refreshEmployee();
        }
    }

    private function refreshEmployee(): void
    {
        $this->employee = $this->employee->fresh([
            'user',
            'salaryHistory.changedBy',
            'leaveRequests.leaveType',
            'leaveBalances.leaveType',
            'payrollAdjustments.recordedBy',
        ]);
    }

    public function render()
    {
        $leaveService = app(LeaveService::class);
        $adjustmentService = app(PayrollAdjustmentService::class);

        $payrollHistory = PayrollLine::query()
            ->with('payrollRun')
            ->where('employee_id', $this->employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', ['processed', 'paid']))
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $periodAdjustments = $adjustmentService->hasTable()
            ? $adjustmentService->forEmployeePeriod(
                $this->employee,
                Carbon::parse($this->adj_period_start),
                Carbon::parse($this->adj_period_end)
            )
            : collect();

        return view('inovcom-payroll::livewire.employees.show')
            ->layout('layouts.app', [
                'title' => $this->employee->full_name,
                'subtitle' => 'Fiche employé',
            ])
            ->with([
                'payrollHistory' => $payrollHistory,
                'periodAdjustments' => $periodAdjustments,
                'adjustmentsEnabled' => $adjustmentService->hasTable(),
                'leaveTypes' => $leaveService->activeTypes(),
                'leaveEnabled' => $leaveService->hasTables(),
                'tenantCode' => $this->tenantCode(),
                'canEdit' => $this->can('payroll.employees'),
                'canLeave' => $this->can('payroll.leave'),
                'canAdjust' => $this->can('payroll.adjustments'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
