<?php

namespace InovCom\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Models\EmployeePayrollAdjustment;
use InovCom\Payroll\Models\PayrollRun;

class PayrollAdjustmentService
{
    public function hasTable(): bool
    {
        return Schema::connection('tenant')->hasTable('employee_payroll_adjustments');
    }

    /**
     * @return Collection<int, EmployeePayrollAdjustment>
     */
    public function forEmployeePeriod(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        if (!$this->hasTable()) {
            return collect();
        }

        return EmployeePayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereDate('period_end', '>=', $from->toDateString())
            ->orderBy('type')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, EmployeePayrollAdjustment>
     */
    public function forRun(PayrollRun $run): Collection
    {
        if (!$this->hasTable()) {
            return collect();
        }

        return EmployeePayrollAdjustment::query()
            ->with(['employee', 'recordedBy'])
            ->whereDate('period_start', '<=', $run->period_end->toDateString())
            ->whereDate('period_end', '>=', $run->period_start->toDateString())
            ->orderBy('employee_id')
            ->orderBy('type')
            ->get();
    }

    public function attachToRun(PayrollRun $run): void
    {
        if (!$this->hasTable() || !$run->isDraft()) {
            return;
        }

        EmployeePayrollAdjustment::query()
            ->whereNull('payroll_run_id')
            ->whereDate('period_start', '<=', $run->period_end->toDateString())
            ->whereDate('period_end', '>=', $run->period_start->toDateString())
            ->update(['payroll_run_id' => $run->id]);
    }

    /**
     * @return array{success: bool, message: string, adjustment: ?EmployeePayrollAdjustment}
     */
    public function recordUnpaidDays(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        float $days,
        string $reason,
        ?int $recordedBy
    ): array {
        if (!$this->hasTable()) {
            return ['success' => false, 'message' => 'Module ajustements non installé.', 'adjustment' => null];
        }

        if ($days <= 0) {
            return ['success' => false, 'message' => 'Indiquez au moins un jour non payé.', 'adjustment' => null];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'Le motif est obligatoire.', 'adjustment' => null];
        }

        $adjustment = EmployeePayrollAdjustment::create([
            'employee_id' => $employee->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'type' => EmployeePayrollAdjustment::TYPE_UNPAID_DAYS,
            'days' => $days,
            'amount' => null,
            'label' => $reason,
            'recorded_by' => $recordedBy,
        ]);

        return ['success' => true, 'message' => 'Jours non payés enregistrés.', 'adjustment' => $adjustment];
    }

    /**
     * @return array{success: bool, message: string, adjustment: ?EmployeePayrollAdjustment}
     */
    public function recordBonus(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        float $amount,
        string $reason,
        ?int $recordedBy
    ): array {
        return $this->recordAmountAdjustment(
            $employee,
            $periodStart,
            $periodEnd,
            EmployeePayrollAdjustment::TYPE_BONUS,
            $amount,
            $reason,
            $recordedBy
        );
    }

    /**
     * @return array{success: bool, message: string, adjustment: ?EmployeePayrollAdjustment}
     */
    public function recordDeduction(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        float $amount,
        string $reason,
        ?int $recordedBy
    ): array {
        return $this->recordAmountAdjustment(
            $employee,
            $periodStart,
            $periodEnd,
            EmployeePayrollAdjustment::TYPE_DEDUCTION,
            $amount,
            $reason,
            $recordedBy
        );
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function delete(int $adjustmentId): array
    {
        if (!$this->hasTable()) {
            return ['success' => false, 'message' => 'Module ajustements non installé.'];
        }

        $adjustment = EmployeePayrollAdjustment::find($adjustmentId);
        if (!$adjustment) {
            return ['success' => false, 'message' => 'Ajustement introuvable.'];
        }

        if ($adjustment->isLocked()) {
            return ['success' => false, 'message' => 'Impossible de supprimer : paie déjà traitée.'];
        }

        $adjustment->delete();

        return ['success' => true, 'message' => 'Ajustement supprimé.'];
    }

    /**
     * @return array{success: bool, message: string, adjustment: ?EmployeePayrollAdjustment}
     */
    private function recordAmountAdjustment(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $type,
        float $amount,
        string $reason,
        ?int $recordedBy
    ): array {
        if (!$this->hasTable()) {
            return ['success' => false, 'message' => 'Module ajustements non installé.', 'adjustment' => null];
        }

        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Le montant doit être supérieur à zéro.', 'adjustment' => null];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'Le motif est obligatoire.', 'adjustment' => null];
        }

        $adjustment = EmployeePayrollAdjustment::create([
            'employee_id' => $employee->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'type' => $type,
            'days' => null,
            'amount' => $amount,
            'label' => $reason,
            'recorded_by' => $recordedBy,
        ]);

        $msg = $type === EmployeePayrollAdjustment::TYPE_BONUS
            ? 'Prime enregistrée.'
            : 'Retenue enregistrée.';

        return ['success' => true, 'message' => $msg, 'adjustment' => $adjustment];
    }
}
