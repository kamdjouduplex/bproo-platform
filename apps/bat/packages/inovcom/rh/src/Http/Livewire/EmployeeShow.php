<?php

namespace InovCom\Rh\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Carbon\Carbon;
use InovCom\Rh\Models\Employee;
use InovCom\Rh\Models\Leave;
use InovCom\Rh\Models\Payslip;
use Livewire\Component;

class EmployeeShow extends Component
{
    use AuthorizesWithTenant;

    public Employee $employee;
    public string   $activeTab = 'infos';

    // ─── Payslip modal ────────────────────────────────────────────────────────
    public bool   $showPayslipModal = false;
    public ?int   $editPayslipId    = null;
    public int    $payMonth         = 1;
    public int    $payYear          = 2026;
    public string $payBaseSalary    = '';
    public array  $payAdditions     = [];
    public array  $payDeductions    = [];
    public string $payStatus        = 'draft';
    public string $payNotes         = '';

    // ─── Leave modal ──────────────────────────────────────────────────────────
    public bool   $showLeaveModal = false;
    public ?int   $editLeaveId    = null;
    public string $leaveType      = 'annual';
    public string $leaveStart     = '';
    public string $leaveEnd       = '';
    public int    $leaveDays      = 1;
    public string $leaveReason    = '';
    public string $leaveNotes     = '';

    public function mount(Employee $employee): void
    {
        $this->tenantAuthorize('rh.view');
        $this->employee    = $employee;
        $this->payMonth    = (int) now()->format('n');
        $this->payYear     = (int) now()->format('Y');
        $this->payBaseSalary = (string) $employee->base_salary;
        $this->leaveStart  = now()->toDateString();
        $this->leaveEnd    = now()->toDateString();
    }

    // ─── Payslip helpers ──────────────────────────────────────────────────────

    public function openCreatePayslipModal(): void
    {
        $this->tenantAuthorize('rh.payroll');
        $this->resetValidation();
        $this->editPayslipId  = null;
        $this->payMonth       = (int) now()->format('n');
        $this->payYear        = (int) now()->format('Y');
        $this->payBaseSalary  = (string) $this->employee->base_salary;
        $this->payAdditions   = [];
        $this->payDeductions  = [];
        $this->payStatus      = 'draft';
        $this->payNotes       = '';
        $this->showPayslipModal = true;
    }

    public function openEditPayslipModal(int $id): void
    {
        $this->tenantAuthorize('rh.payroll');
        $this->resetValidation();
        $payslip = Payslip::on('tenant')->findOrFail($id);

        $this->editPayslipId  = $id;
        $this->payMonth       = $payslip->period_month;
        $this->payYear        = $payslip->period_year;
        $this->payBaseSalary  = (string) $payslip->base_salary;
        $this->payAdditions   = $payslip->additions ?? [];
        $this->payDeductions  = $payslip->deductions ?? [];
        $this->payStatus      = $payslip->status;
        $this->payNotes       = $payslip->notes ?? '';
        $this->showPayslipModal = true;
    }

    public function closePayslipModal(): void
    {
        $this->showPayslipModal = false;
        $this->editPayslipId    = null;
    }

    public function addAdditionRow(): void
    {
        $this->payAdditions[] = ['label' => '', 'amount' => 0];
    }

    public function removeAdditionRow(int $index): void
    {
        array_splice($this->payAdditions, $index, 1);
        $this->payAdditions = array_values($this->payAdditions);
    }

    public function addDeductionRow(): void
    {
        $this->payDeductions[] = ['label' => '', 'amount' => 0];
    }

    public function removeDeductionRow(int $index): void
    {
        array_splice($this->payDeductions, $index, 1);
        $this->payDeductions = array_values($this->payDeductions);
    }

    public function savePayslip(): void
    {
        $this->tenantAuthorize('rh.payroll');
        $this->validate([
            'payMonth'       => 'required|integer|min:1|max:12',
            'payYear'        => 'required|integer|min:2000|max:2100',
            'payBaseSalary'  => 'required|numeric|min:0',
        ]);

        $additions  = collect($this->payAdditions)->filter(fn($r) => !empty($r['label']))->values()->toArray();
        $deductions = collect($this->payDeductions)->filter(fn($r) => !empty($r['label']))->values()->toArray();
        $addTotal   = collect($additions)->sum(fn($r) => (float) ($r['amount'] ?? 0));
        $dedTotal   = collect($deductions)->sum(fn($r) => (float) ($r['amount'] ?? 0));
        $gross      = (float) $this->payBaseSalary + $addTotal;
        $net        = $gross - $dedTotal;

        $data = [
            'employee_id'  => $this->employee->id,
            'period_month' => $this->payMonth,
            'period_year'  => $this->payYear,
            'base_salary'  => (float) $this->payBaseSalary,
            'additions'    => $additions ?: null,
            'deductions'   => $deductions ?: null,
            'gross_salary' => $gross,
            'net_salary'   => $net,
            'status'       => $this->payStatus,
            'notes'        => $this->payNotes ?: null,
        ];

        if ($this->editPayslipId) {
            Payslip::on('tenant')->findOrFail($this->editPayslipId)->update($data);
            notify()->success(__('Fiche de paie mise à jour.'));
        } else {
            $data['code'] = Payslip::generateCode();
            Payslip::on('tenant')->create($data);
            notify()->success(__('Fiche de paie créée.'));
        }

        $this->closePayslipModal();
    }

    public function validatePayslip(int $id): void
    {
        $this->tenantAuthorize('rh.payroll');
        Payslip::on('tenant')->findOrFail($id)->update(['status' => 'validated']);
        notify()->success(__('Fiche validée.'));
    }

    public function markPayslipPaid(int $id): void
    {
        $this->tenantAuthorize('rh.payroll');
        Payslip::on('tenant')->findOrFail($id)->update([
            'status'  => 'paid',
            'paid_at' => now()->toDateString(),
        ]);
        notify()->success(__('Paiement enregistré.'));
    }

    public function deletePayslip(int $id): void
    {
        $this->tenantAuthorize('rh.payroll');
        Payslip::on('tenant')->findOrFail($id)->delete();
        notify()->success(__('Fiche supprimée.'));
    }

    // ─── Leave helpers ────────────────────────────────────────────────────────

    public function openCreateLeaveModal(): void
    {
        $this->tenantAuthorize('rh.create');
        $this->resetValidation();
        $this->editLeaveId   = null;
        $this->leaveType     = 'annual';
        $this->leaveStart    = now()->toDateString();
        $this->leaveEnd      = now()->toDateString();
        $this->leaveDays     = 1;
        $this->leaveReason   = '';
        $this->leaveNotes    = '';
        $this->showLeaveModal = true;
    }

    public function openEditLeaveModal(int $id): void
    {
        $this->tenantAuthorize('rh.edit');
        $this->resetValidation();
        $leave = Leave::on('tenant')->findOrFail($id);

        $this->editLeaveId   = $id;
        $this->leaveType     = $leave->type;
        $this->leaveStart    = $leave->start_date->format('Y-m-d');
        $this->leaveEnd      = $leave->end_date->format('Y-m-d');
        $this->leaveDays     = $leave->days_count;
        $this->leaveReason   = $leave->reason ?? '';
        $this->leaveNotes    = $leave->notes ?? '';
        $this->showLeaveModal = true;
    }

    public function closeLeaveModal(): void
    {
        $this->showLeaveModal = false;
        $this->editLeaveId    = null;
    }

    public function updatedLeaveStart(): void
    {
        $this->recalcLeaveDays();
    }

    public function updatedLeaveEnd(): void
    {
        $this->recalcLeaveDays();
    }

    private function recalcLeaveDays(): void
    {
        if ($this->leaveStart && $this->leaveEnd) {
            try {
                $start = Carbon::parse($this->leaveStart);
                $end   = Carbon::parse($this->leaveEnd);
                if ($end >= $start) {
                    $this->leaveDays = (int) $end->diffInDays($start) + 1;
                }
            } catch (\Exception) {}
        }
    }

    public function saveLeave(): void
    {
        if ($this->editLeaveId) {
            $this->tenantAuthorize('rh.edit');
        } else {
            $this->tenantAuthorize('rh.create');
        }

        $this->validate([
            'leaveType'   => 'required|in:annual,sick,maternity,paternity,unpaid,other',
            'leaveStart'  => 'required|date',
            'leaveEnd'    => 'required|date|after_or_equal:leaveStart',
            'leaveDays'   => 'required|integer|min:1',
            'leaveReason' => 'nullable|string',
        ]);

        $data = [
            'employee_id' => $this->employee->id,
            'type'        => $this->leaveType,
            'start_date'  => $this->leaveStart,
            'end_date'    => $this->leaveEnd,
            'days_count'  => $this->leaveDays,
            'reason'      => $this->leaveReason ?: null,
            'notes'       => $this->leaveNotes ?: null,
        ];

        if ($this->editLeaveId) {
            Leave::on('tenant')->findOrFail($this->editLeaveId)->update($data);
            notify()->success(__('Congé mis à jour.'));
        } else {
            $data['status'] = 'pending';
            Leave::on('tenant')->create($data);
            notify()->success(__('Demande de congé enregistrée.'));
        }

        $this->closeLeaveModal();
    }

    public function approveLeave(int $id): void
    {
        $this->tenantAuthorize('rh.leave_approve');
        Leave::on('tenant')->findOrFail($id)->update([
            'status'      => 'approved',
            'approved_by' => auth('tenant')->id(),
            'approved_at' => now(),
        ]);
        notify()->success(__('Congé approuvé.'));
    }

    public function rejectLeave(int $id): void
    {
        $this->tenantAuthorize('rh.leave_approve');
        Leave::on('tenant')->findOrFail($id)->update([
            'status'      => 'rejected',
            'approved_by' => auth('tenant')->id(),
            'approved_at' => now(),
        ]);
        notify()->info(__('Congé refusé.'));
    }

    public function deleteLeave(int $id): void
    {
        $this->tenantAuthorize('rh.delete');
        Leave::on('tenant')->findOrFail($id)->delete();
        notify()->success(__('Congé supprimé.'));
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $payslips = Payslip::on('tenant')
            ->where('employee_id', $this->employee->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get();

        $leaves = Leave::on('tenant')
            ->where('employee_id', $this->employee->id)
            ->orderByDesc('start_date')
            ->get();

        $leaveStatsYear = Leave::on('tenant')
            ->where('employee_id', $this->employee->id)
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->sum('days_count');

        $pendingLeaves = Leave::on('tenant')
            ->where('employee_id', $this->employee->id)
            ->where('status', 'pending')
            ->count();

        $tenantCode = request()->query('tenant') ?? session('tenant_code');

        return view('inovcom-rh::livewire.employees.show', [
            'payslips'        => $payslips,
            'leaves'          => $leaves,
            'leaveStatsYear'  => $leaveStatsYear,
            'pendingLeaves'   => $pendingLeaves,
            'months'          => Payslip::allMonths(),
            'leaveTypes'      => Leave::types(),
            'tenantCode'      => $tenantCode,
            'canEdit'         => $this->tenantCan('rh.edit'),
            'canDelete'       => $this->tenantCan('rh.delete'),
            'canPayroll'      => $this->tenantCan('rh.payroll'),
            'canApproveLeave' => $this->tenantCan('rh.leave_approve'),
            'canCreate'       => $this->tenantCan('rh.create'),
        ])->layout('layouts.app', [
            'title'    => $this->employee->fullName(),
            'subtitle' => $this->employee->code . ' · ' . ($this->employee->position ?? __('Employé')),
        ]);
    }
}
