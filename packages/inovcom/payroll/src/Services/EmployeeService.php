<?php

namespace InovCom\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Models\EmployeeSalaryHistory;

class EmployeeService
{
    public function nextEmployeeNumber(): string
    {
        $last = Employee::query()
            ->where('employee_number', 'like', 'EMP-%')
            ->orderByDesc('id')
            ->value('employee_number');

        $next = 1;
        if ($last && preg_match('/EMP-(\d+)/', (string) $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return 'EMP-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function salaryAtDate(Employee $employee, Carbon|string $date): float
    {
        $date = Carbon::parse($date);

        if (Schema::connection('tenant')->hasTable('employee_salary_history')) {
            $history = EmployeeSalaryHistory::query()
                ->where('employee_id', $employee->id)
                ->whereDate('effective_date', '<=', $date)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->first();

            if ($history) {
                return (float) $history->amount;
            }
        }

        return (float) $employee->base_salary;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $changedBy = null): Employee
    {
        if (empty($data['employee_number'])) {
            $data['employee_number'] = $this->nextEmployeeNumber();
        }

        $employee = Employee::create($data);
        $this->recordInitialSalary($employee, $changedBy);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, ?int $changedBy = null, ?string $salaryReason = null): Employee
    {
        $oldSalary = (float) $employee->base_salary;
        $newSalary = isset($data['base_salary']) ? (float) $data['base_salary'] : $oldSalary;

        $employee->update($data);

        if (abs($newSalary - $oldSalary) > 0.001) {
            $this->recordSalaryChange($employee, $newSalary, $oldSalary, $salaryReason, $changedBy);
        }

        return $employee->fresh();
    }

    private function recordInitialSalary(Employee $employee, ?int $changedBy): void
    {
        if (!Schema::connection('tenant')->hasTable('employee_salary_history')) {
            return;
        }

        EmployeeSalaryHistory::create([
            'employee_id' => $employee->id,
            'amount' => $employee->base_salary,
            'previous_amount' => null,
            'effective_date' => $employee->hire_date ?? now()->toDateString(),
            'reason' => 'Salaire initial',
            'changed_by' => $changedBy,
        ]);
    }

    private function recordSalaryChange(
        Employee $employee,
        float $newAmount,
        float $previousAmount,
        ?string $reason,
        ?int $changedBy
    ): void {
        if (!Schema::connection('tenant')->hasTable('employee_salary_history')) {
            return;
        }

        EmployeeSalaryHistory::create([
            'employee_id' => $employee->id,
            'amount' => $newAmount,
            'previous_amount' => $previousAmount,
            'effective_date' => now()->toDateString(),
            'reason' => $reason ?: 'Augmentation / révision salariale',
            'changed_by' => $changedBy,
        ]);
    }
}
